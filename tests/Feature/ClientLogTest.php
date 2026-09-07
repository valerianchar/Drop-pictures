<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClientLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_browser_can_leave_a_trace_of_what_never_reached_the_server(): void
    {
        Log::spy();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/journal-client', ['event' => 'picker.cancelled', 'data' => ['standalone' => true, 'visibility' => 'visible']])
            ->assertNoContent();

        Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Client : picker.cancelled'
            && $context['user_id'] === $user->id
            && $context['standalone'] === true);
    }

    public function test_the_event_name_is_constrained(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/journal-client', ['event' => 'n’importe quoi <script>'])
            ->assertUnprocessable();
    }

    public function test_visitors_are_refused(): void
    {
        $this->postJson('/journal-client', ['event' => 'x'])->assertUnauthorized();
    }

    public function test_a_refused_upload_is_logged_server_side(): void
    {
        Log::spy();
        config(['drop.max_file_bytes' => 10]);

        $this->actingAs(User::factory()->create())
            ->postJson('/depots', ['name' => 'film.mov', 'size' => 5000, 'type' => 'video/quicktime'])
            ->assertUnprocessable();

        Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Dépôt refusé'
            && $context['name'] === 'film.mov' && isset($context['errors']['size']));
    }

    public function test_a_truncated_chunk_is_rolled_back_and_answered_409(): void
    {
        config(['drop.chunk_bytes' => 1000]);
        $user = User::factory()->create();
        $start = $this->actingAs($user)->postJson('/depots', ['name' => 'clip.mp4', 'size' => 3000])->json();

        $this->call('PUT', "/depots/{$start['id']}/morceaux/0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], random_bytes(1000))->assertOk();

        // Le navigateur annonçait 1000 octets, la connexion n'en a laissé passer que 600.
        $this->call('PUT', "/depots/{$start['id']}/morceaux/1", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_CONTENT_LENGTH' => '1000'], random_bytes(600))
            ->assertStatus(409);

        // Le morceau tronqué n'est pas marqué : ses octets n'entrent pas dans le compte.
        $this->assertSame(1000, Upload::query()->firstOrFail()->received_bytes);

        // Renvoyé complet, il passe.
        $this->call('PUT', "/depots/{$start['id']}/morceaux/1", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_CONTENT_LENGTH' => '1000'], random_bytes(1000))
            ->assertOk()
            ->assertJsonPath('received_bytes', 2000);
    }
}
