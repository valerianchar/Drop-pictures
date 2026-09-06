<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Le ZIP est un emballage, pas une transformation : chaque entrée est stockée
 * sans compression et rend l'original octet pour octet.
 */
class ZipDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_whole_group_downloads_as_a_stored_zip(): void
    {
        $group = Group::factory()->named('Week-end')->create();
        $member = User::factory()->create();
        $group->addMember($member);

        $first = $this->fileFor($group->owner, 'IMG_1.jpg', random_bytes(3000));
        $second = $this->fileFor($member, 'clip.mp4', random_bytes(5000));
        $group->media()->attach([$first->id, $second->id], ['shared_by' => $group->owner_id]);

        $response = $this->actingAs($member)->get("/groupes/{$group->id}/telecharger")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');

        $this->assertStringContainsString('week-end.zip', $response->headers->get('Content-Disposition'));

        $entries = $this->entriesOf($response);
        $this->assertSame(['IMG_1.jpg', 'clip.mp4'], array_keys($entries));
        $this->assertSame(Storage::disk('local')->get($first->disk_path), $entries['IMG_1.jpg']['bytes']);
        $this->assertSame(Storage::disk('local')->get($second->disk_path), $entries['clip.mp4']['bytes']);
        $this->assertSame([ZipArchive::CM_STORE, ZipArchive::CM_STORE], array_column($entries, 'method'));
    }

    public function test_a_selection_downloads_and_duplicate_names_are_suffixed(): void
    {
        $user = User::factory()->create();
        $a = $this->fileFor($user, 'photo.jpg', 'aaaa');
        $b = $this->fileFor($user, 'photo.jpg', 'bbbb');
        $c = $this->fileFor($user, 'autre.png', 'cccc');

        $response = $this->actingAs($user)
            ->get("/fichiers/telecharger?ids[]={$a->id}&ids[]={$b->id}&ids[]={$c->id}")
            ->assertOk();

        $entries = $this->entriesOf($response);
        $this->assertSame(['photo.jpg', 'photo (2).jpg', 'autre.png'], array_keys($entries));
        $this->assertSame('bbbb', $entries['photo (2).jpg']['bytes']);
    }

    public function test_strangers_get_nothing(): void
    {
        $group = Group::factory()->create();
        $media = $this->fileFor($group->owner, 'x.jpg', 'x');
        $group->media()->attach($media->id, ['shared_by' => $group->owner_id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/groupes/{$group->id}/telecharger")->assertForbidden();
        $this->actingAs($stranger)->get("/fichiers/telecharger?ids[]={$media->id}")->assertForbidden();
        $this->actingAs($stranger)->get('/fichiers/telecharger')->assertNotFound();
    }

    private function fileFor(User $user, string $name, string $bytes): Media
    {
        $media = Media::factory()->for($user)->named($name)->create(['size_bytes' => strlen($bytes), 'checksum_sha256' => hash('sha256', $bytes)]);
        Storage::disk('local')->put($media->disk_path, $bytes);

        return $media;
    }

    /**
     * @return array<string, array{bytes: string, method: int}>
     */
    private function entriesOf(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));

        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entries[$stat['name']] = ['bytes' => $zip->getFromIndex($i), 'method' => $stat['comp_method']];
        }

        $zip->close();
        unlink($path);

        return $entries;
    }
}
