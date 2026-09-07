<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Les morceaux partent à plusieurs de front : ils arrivent donc dans le
 * désordre. Chacun s'écrit à sa place, et l'empreinte — qui, elle, doit être
 * calculée dans l'ordre — n'avance que sur le préfixe contigu déjà reçu. Le
 * fichier rendu reste identique à l'octet.
 */
class ParallelUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['drop.chunk_bytes' => 1000]);
    }

    public function test_the_file_is_identical_whatever_the_order(): void
    {
        $user = User::factory()->create();
        $bytes = random_bytes(2500);
        $start = $this->actingAs($user)->postJson('/depots', ['name' => 'clip.mp4', 'size' => 2500])->json();

        foreach ([2, 0, 1] as $index) {
            $this->sendChunk($start['id'], $index, substr($bytes, $index * 1000, 1000));
        }

        $upload = Upload::query()->firstOrFail();
        $this->assertSame(2500, $upload->received_bytes);
        $this->assertSame(3, $upload->next_chunk_index, 'l’empreinte a couru jusqu’au bout');
        $this->assertSame('111', $upload->arrived);

        $response = $this->postJson("/depots/{$start['id']}/terminer", ['checksum' => hash('sha256', $bytes)])
            ->assertCreated();

        $media = Media::query()->firstOrFail();
        $this->assertSame(hash('sha256', $bytes), $media->checksum_sha256);
        $this->assertSame($bytes, Storage::disk('local')->get($media->disk_path));
        $this->assertSame(2500, $response->json('media.size_bytes'));
    }

    public function test_the_checksum_waits_for_the_hole_to_be_filled(): void
    {
        $user = User::factory()->create();
        $bytes = random_bytes(3000);
        $start = $this->actingAs($user)->postJson('/depots', ['name' => 'a.jpg', 'size' => 3000])->json();

        // Le trou du milieu laisse l'empreinte au premier morceau.
        $this->sendChunk($start['id'], 0, substr($bytes, 0, 1000));
        $this->sendChunk($start['id'], 2, substr($bytes, 2000, 1000));

        $upload = Upload::query()->firstOrFail();
        $this->assertSame(1, $upload->next_chunk_index);
        $this->assertSame('101', $upload->arrived);
        $this->assertSame([1], $upload->missingChunks());

        // Incomplet : la clôture refuse.
        $this->postJson("/depots/{$start['id']}/terminer", ['checksum' => hash('sha256', $bytes)])
            ->assertUnprocessable();

        // Le trou comblé, l'empreinte rattrape tout d'un coup.
        $this->sendChunk($start['id'], 1, substr($bytes, 1000, 1000));
        $this->assertSame(3, Upload::query()->firstOrFail()->next_chunk_index);

        $this->postJson("/depots/{$start['id']}/terminer", ['checksum' => hash('sha256', $bytes)])
            ->assertCreated();
    }

    public function test_a_wrong_length_is_refused_and_the_chunk_stays_missing(): void
    {
        $user = User::factory()->create();
        $start = $this->actingAs($user)->postJson('/depots', ['name' => 'a.jpg', 'size' => 3000])->json();

        // Le navigateur annonce autre chose que la taille du morceau.
        $this->call('PUT', "/depots/{$start['id']}/morceaux/0", [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_CONTENT_LENGTH' => '600',
        ], random_bytes(600))->assertUnprocessable();

        $this->assertSame('000', Upload::query()->firstOrFail()->arrivedMask());
    }

    public function test_a_chunk_cut_in_flight_is_not_marked_and_can_be_resent(): void
    {
        $user = User::factory()->create();
        $bytes = random_bytes(2000);
        $start = $this->actingAs($user)->postJson('/depots', ['name' => 'a.jpg', 'size' => 2000])->json();

        // Annoncé complet, arrivé tronqué : refusé, et rien n'est marqué.
        $this->call('PUT', "/depots/{$start['id']}/morceaux/1", [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_CONTENT_LENGTH' => '1000',
        ], substr($bytes, 1000, 400))->assertStatus(409);

        $upload = Upload::query()->firstOrFail();
        $this->assertSame('00', $upload->arrivedMask());
        $this->assertSame(0, $upload->received_bytes);

        // Renvoyé complet, il passe — et les octets tronqués ont été recouverts.
        $this->sendChunk($start['id'], 1, substr($bytes, 1000, 1000));
        $this->sendChunk($start['id'], 0, substr($bytes, 0, 1000));

        $this->postJson("/depots/{$start['id']}/terminer", ['checksum' => hash('sha256', $bytes)])
            ->assertCreated();

        $this->assertSame($bytes, Storage::disk('local')->get(Media::query()->firstOrFail()->disk_path));
    }

    public function test_a_body_longer_than_the_chunk_is_refused(): void
    {
        $user = User::factory()->create();
        $start = $this->actingAs($user)->postJson('/depots', ['name' => 'a.jpg', 'size' => 3000])->json();

        $this->call('PUT', "/depots/{$start['id']}/morceaux/0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], random_bytes(1500))
            ->assertUnprocessable();

        $this->assertSame('000', Upload::query()->firstOrFail()->arrivedMask());
    }

    public function test_an_upload_opened_before_parallel_sending_still_finishes(): void
    {
        $user = User::factory()->create();
        $bytes = random_bytes(2000);

        // Un dépôt d'avant la mise à jour : pas de masque, un préfixe contigu.
        $this->sendChunkTo($upload = Upload::factory()->for($user)->create([
            'size_bytes' => 2000,
            'chunk_bytes' => 1000,
            'received_bytes' => 0,
            'next_chunk_index' => 0,
            'arrived' => null,
        ]), 0, substr($bytes, 0, 1000));

        $this->assertSame('10', $upload->refresh()->arrivedMask());

        $this->sendChunkTo($upload, 1, substr($bytes, 1000));

        $this->actingAs($user)->postJson("/depots/{$upload->uuid}/terminer", ['checksum' => hash('sha256', $bytes)])
            ->assertCreated();
    }

    private function sendChunk(string $uuid, int $index, string $bytes): void
    {
        $this->call('PUT', "/depots/{$uuid}/morceaux/{$index}", [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_CONTENT_LENGTH' => (string) strlen($bytes),
        ], $bytes)->assertOk();
    }

    private function sendChunkTo(Upload $upload, int $index, string $bytes): void
    {
        Storage::disk('local')->put($upload->part_path, Storage::disk('local')->exists($upload->part_path)
            ? Storage::disk('local')->get($upload->part_path)
            : '');

        $this->actingAs($upload->user);
        $this->sendChunk($upload->uuid, $index, $bytes);
    }
}
