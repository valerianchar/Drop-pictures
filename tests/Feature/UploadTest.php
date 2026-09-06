<?php

namespace Tests\Feature;

use App\Jobs\ProcessMedia;
use App\Models\Media;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Le critère d'acceptation n°1 : ce qui est déposé est rendu octet pour octet.
 */
class UploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_file_uploaded_in_chunks_is_stored_byte_for_byte(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        // Des octets aléatoires : rien qu'un réencodage pourrait laisser intact par hasard.
        $original = random_bytes(3 * 1024 + 517);
        $checksum = hash('sha256', $original);

        $start = $this->actingAs($user)
            ->postJson('/depots', ['name' => 'IMG_2041.jpg', 'size' => strlen($original), 'type' => 'image/jpeg'])
            ->assertCreated()
            ->json();

        $chunkBytes = 1024;

        foreach (str_split($original, $chunkBytes) as $index => $chunk) {
            $this->call('PUT', "/depots/{$start['id']}/morceaux/{$index}", [], [], [], [
                'CONTENT_TYPE' => 'application/octet-stream',
            ], $chunk)->assertOk();
        }

        $response = $this->postJson("/depots/{$start['id']}/terminer", [
            'checksum' => $checksum,
            'tags' => ['Portraits'],
        ])->assertCreated();

        $media = Media::query()->firstOrFail();

        $this->assertSame($checksum, $media->checksum_sha256);
        $this->assertSame(strlen($original), $media->size_bytes);
        $this->assertSame('IMG_2041.jpg', $media->original_name);
        $this->assertSame($original, Storage::disk('local')->get($media->disk_path));
        $this->assertSame(['Portraits'], $response->json('media.tags'));
        $this->assertDatabaseCount('uploads', 0);

        Queue::assertPushed(ProcessMedia::class);

        // Le téléchargement rend exactement le fichier déposé, sous son nom.
        $download = $this->actingAs($user)->get("/fichiers/{$media->id}/telecharger")
            ->assertOk()
            ->assertHeader('X-Checksum-SHA256', $checksum)
            ->assertDownload('IMG_2041.jpg');

        $this->assertSame($checksum, hash('sha256', file_get_contents($download->getFile()->getPathname())));
    }

    public function test_a_checksum_mismatch_rejects_the_upload_and_discards_the_file(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $original = random_bytes(600);

        $start = $this->actingAs($user)
            ->postJson('/depots', ['name' => 'nuit.raw', 'size' => 600])
            ->json();

        $this->call('PUT', "/depots/{$start['id']}/morceaux/0", [], [], [], [], $original)->assertOk();

        $this->postJson("/depots/{$start['id']}/terminer", ['checksum' => str_repeat('a', 64)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('checksum');

        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('uploads', 0);
        Storage::disk('local')->assertMissing("uploads/{$start['id']}.part");
    }

    public function test_an_incomplete_upload_cannot_be_finished(): void
    {
        $user = User::factory()->create();
        $start = $this->actingAs($user)->postJson('/depots', ['name' => 'a.jpg', 'size' => 2000])->json();

        $this->call('PUT', "/depots/{$start['id']}/morceaux/0", [], [], [], [], random_bytes(1000))->assertOk();

        $this->postJson("/depots/{$start['id']}/terminer", ['checksum' => str_repeat('a', 64)])
            ->assertUnprocessable();

        $this->assertDatabaseCount('media', 0);
    }

    public function test_an_out_of_order_chunk_is_refused_and_a_repeated_one_ignored(): void
    {
        $user = User::factory()->create();
        $start = $this->actingAs($user)->postJson('/depots', ['name' => 'a.jpg', 'size' => 3000])->json();

        $this->call('PUT', "/depots/{$start['id']}/morceaux/1", [], [], [], [], random_bytes(1000))
            ->assertUnprocessable();

        $this->call('PUT', "/depots/{$start['id']}/morceaux/0", [], [], [], [], random_bytes(1000))->assertOk();
        $this->call('PUT', "/depots/{$start['id']}/morceaux/0", [], [], [], [], random_bytes(1000))
            ->assertOk()
            ->assertJsonPath('received_bytes', 1000);
    }

    public function test_a_file_over_the_quota_is_refused(): void
    {
        config(['drop.quota_bytes' => 5000]);
        $user = User::factory()->create();
        Media::factory()->for($user)->sized(4000)->create();

        $this->actingAs($user)
            ->postJson('/depots', ['name' => 'trop.jpg', 'size' => 2000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('size');
    }

    public function test_a_file_over_the_maximum_size_is_refused(): void
    {
        config(['drop.max_file_bytes' => 1000]);

        $this->actingAs(User::factory()->create())
            ->postJson('/depots', ['name' => 'trop.mov', 'size' => 1001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('size');
    }

    public function test_a_path_in_the_name_is_reduced_to_its_basename(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/depots', ['name' => '../../etc/passwd', 'size' => 10])->assertCreated();

        $this->assertSame('passwd', Upload::query()->firstOrFail()->original_name);
    }

    public function test_an_upload_belongs_to_its_user_only(): void
    {
        $owner = User::factory()->create();
        $start = $this->actingAs($owner)->postJson('/depots', ['name' => 'a.jpg', 'size' => 10])->json();

        $this->actingAs(User::factory()->create())
            ->call('PUT', "/depots/{$start['id']}/morceaux/0", [], [], [], [], 'abc')
            ->assertNotFound();
    }

    public function test_an_upload_can_be_cancelled(): void
    {
        $user = User::factory()->create();
        $start = $this->actingAs($user)->postJson('/depots', ['name' => 'a.jpg', 'size' => 10])->json();

        $this->deleteJson("/depots/{$start['id']}")->assertNoContent();

        $this->assertDatabaseCount('uploads', 0);
        Storage::disk('local')->assertMissing("uploads/{$start['id']}.part");
    }
}
