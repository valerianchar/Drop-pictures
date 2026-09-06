<?php

namespace Tests\Feature;

use App\Events\MediaProcessed;
use App\Events\MediaRestored;
use App\Jobs\ArchiveMedia;
use App\Jobs\RestoreMedia;
use App\Models\Group;
use App\Models\Media;
use App\Models\User;
use App\Support\ColdStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * L'archivage sans perte joue la vraie recompression : ces tests exigent cjxl,
 * djxl et zstd (présents dans l'image et en CI) et sont sautés sans eux.
 */
class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! ColdStorage::available()) {
            $this->markTestSkipped('cjxl, djxl ou zstd absents.');
        }

        config(['drop.archive_min_bytes' => 1000, 'drop.archive_min_saving' => 0.05]);
    }

    public function test_a_jpeg_is_archived_losslessly_and_rebuilt_on_download(): void
    {
        Event::fake([MediaProcessed::class, MediaRestored::class]);
        $user = User::factory()->create();
        [$media, $bytes] = $this->photo($user, 'jpg');

        (new ArchiveMedia($media))->handle();

        $media->refresh();
        $this->assertTrue($media->isArchived());
        $this->assertSame(ColdStorage::JXL, $media->archive_codec);
        $this->assertLessThan($media->size_bytes, $media->archived_bytes);
        Storage::disk('local')->assertExists($media->archive_path);
        Storage::disk('local')->assertMissing($media->disk_path);
        Event::assertDispatched(MediaProcessed::class);

        // Le téléchargement reconstruit l'original à la volée : mêmes octets, même empreinte.
        $response = $this->actingAs($user)->get("/fichiers/{$media->id}/telecharger")
            ->assertOk()
            ->assertHeader('X-Checksum-SHA256', $media->checksum_sha256);
        $this->assertSame($bytes, file_get_contents($response->getFile()->getPathname()));

        $media->refresh();
        $this->assertFalse($media->isArchived());
        Storage::disk('local')->assertExists($media->disk_path);
        Storage::disk('local')->assertMissing("archives/{$media->uuid}.jxl");
        $this->assertNotNull($media->last_accessed_at);
        Event::assertDispatched(MediaRestored::class);
    }

    public function test_a_bitmap_is_archived_with_zstd(): void
    {
        Event::fake();
        [$media, $bytes] = $this->photo(User::factory()->create(), 'bmp');

        (new ArchiveMedia($media))->handle();

        $this->assertSame(ColdStorage::ZSTD, $media->fresh()->archive_codec);
        Storage::disk('local')->assertExists("archives/{$media->uuid}.zst");

        (new RestoreMedia($media->fresh()))->handle();

        $this->assertSame($bytes, Storage::disk('local')->get($media->disk_path));
        $this->assertNull($media->fresh()->archived_at);
    }

    public function test_the_gallery_shows_the_archive_and_the_restore_button_works(): void
    {
        Event::fake();
        Queue::fake([RestoreMedia::class]);
        $user = User::factory()->create();
        [$media] = $this->photo($user, 'jpg');
        (new ArchiveMedia($media))->handle();

        $this->actingAs($user)->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('media.0.archived', true)
                ->where('media.0.archived_label', fn (string $label) => str_starts_with($label, 'Archivé · −'))
                ->where('storage.saved_label', fn ($label) => $label !== null));

        $this->actingAs($user)->post("/fichiers/{$media->id}/restaurer")->assertRedirect();
        Queue::assertPushed(RestoreMedia::class);
    }

    public function test_nothing_is_archived_when_the_saving_is_too_small(): void
    {
        Event::fake();
        config(['drop.archive_min_saving' => 0.99]);
        [$media, $bytes] = $this->photo(User::factory()->create(), 'jpg');

        (new ArchiveMedia($media))->handle();

        $media->refresh();
        $this->assertFalse($media->isArchived());
        $this->assertNotNull($media->archive_skipped_at);
        $this->assertSame($bytes, Storage::disk('local')->get($media->disk_path));
        $this->assertEmpty(Storage::disk('local')->files('archives'));
    }

    public function test_the_command_picks_old_untouched_photos_outside_expiring_groups(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        config(['drop.archive_after_days' => 7]);

        $old = Media::factory()->for($user)->create(['created_at' => now()->subDays(10), 'size_bytes' => 5_000_000]);
        $recent = Media::factory()->for($user)->create(['created_at' => now()->subDays(2), 'size_bytes' => 5_000_000]);
        $viewed = Media::factory()->for($user)->create(['created_at' => now()->subDays(20), 'last_accessed_at' => now()->subDay(), 'size_bytes' => 5_000_000]);
        $video = Media::factory()->for($user)->video()->create(['created_at' => now()->subDays(30)]);
        $tiny = Media::factory()->for($user)->create(['created_at' => now()->subDays(30), 'size_bytes' => 100]);
        $doomed = Media::factory()->for($user)->create(['created_at' => now()->subDays(30), 'size_bytes' => 5_000_000]);
        Group::factory()->expiring(now()->addDays(2))->create()->media()->attach($doomed->id, ['shared_by' => $user->id, 'uploaded_here' => true]);

        $this->artisan('drop:archive')->assertSuccessful();

        Queue::assertPushed(ArchiveMedia::class, fn (ArchiveMedia $job) => $job->media->is($old));
        Queue::assertNotPushed(ArchiveMedia::class, fn (ArchiveMedia $job) => $job->media->is($recent)
            || $job->media->is($viewed) || $job->media->is($video) || $job->media->is($tiny) || $job->media->is($doomed));
    }

    public function test_a_zero_delay_disables_archiving(): void
    {
        Queue::fake();
        config(['drop.archive_after_days' => 0]);
        Media::factory()->create(['created_at' => now()->subYear(), 'size_bytes' => 5_000_000]);

        $this->artisan('drop:archive')->expectsOutputToContain('désactivé')->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_deleting_an_archived_file_removes_its_archive(): void
    {
        Event::fake();
        $user = User::factory()->create();
        [$media] = $this->photo($user, 'jpg');
        (new ArchiveMedia($media))->handle();
        $archive = $media->fresh()->archive_path;

        $this->actingAs($user)->delete("/fichiers/{$media->id}")->assertRedirect('/');

        Storage::disk('local')->assertMissing($archive);
    }

    /**
     * Une vraie image, écrite sur le disque de test, avec son empreinte.
     *
     * @return array{0: Media, 1: string}
     */
    private function photo(User $user, string $extension): array
    {
        $image = imagecreatetruecolor(640, 480);

        for ($y = 0; $y < 480; $y++) {
            imageline($image, 0, $y, 639, $y, imagecolorallocate($image, ($y * 3) % 256, ($y * 5) % 256, ($y * 7) % 256));
        }

        for ($i = 0; $i < 40; $i++) {
            imagefilledellipse($image, ($i * 97) % 640, ($i * 61) % 480, 40 + $i * 3, 30 + $i * 2, imagecolorallocate($image, ($i * 40) % 256, ($i * 90) % 256, ($i * 140) % 256));
        }

        ob_start();
        $extension === 'bmp' ? imagebmp($image, null, false) : imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $media = Media::factory()->for($user)->named("photo.{$extension}")->create([
            'size_bytes' => strlen($bytes),
            'checksum_sha256' => hash('sha256', $bytes),
            'created_at' => now()->subDays(30),
        ]);
        Storage::disk('local')->put($media->disk_path, $bytes);

        return [$media, $bytes];
    }
}
