<?php

namespace Tests\Feature;

use App\Events\MediaAdded;
use App\Events\MediaProcessed;
use App\Events\MediaRemoved;
use App\Models\Group;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_file_dropped_on_a_group_page_lands_in_the_group_and_is_announced(): void
    {
        Event::fake([MediaAdded::class, MediaProcessed::class]);
        $group = Group::factory()->create();
        $member = User::factory()->create();
        $group->addMember($member);
        $bytes = random_bytes(800);

        $start = $this->actingAs($member)->postJson('/depots', ['name' => 'plage.jpg', 'size' => 800])->json();
        $this->call('PUT', "/depots/{$start['id']}/morceaux/0", [], [], [], [], $bytes)->assertOk();
        $this->postJson("/depots/{$start['id']}/terminer", ['checksum' => hash('sha256', $bytes), 'group_id' => $group->id])
            ->assertCreated();

        $media = Media::query()->firstOrFail();
        $this->assertTrue($group->media()->where('media.id', $media->id)->exists());
        $this->assertTrue((bool) $group->media()->first()->pivot->uploaded_here);

        Event::assertDispatched(MediaAdded::class, fn (MediaAdded $event) => $event->group->is($group)
            && $event->media->is($media)
            && $event->actor->is($member)
            && $event->broadcastOn()[0]->name === "private-groups.{$group->id}");
        Event::assertDispatched(MediaProcessed::class);
    }

    public function test_a_stranger_cannot_drop_into_a_group(): void
    {
        $group = Group::factory()->create();
        $stranger = User::factory()->create();
        $bytes = random_bytes(100);

        $start = $this->actingAs($stranger)->postJson('/depots', ['name' => 'x.jpg', 'size' => 100])->json();
        $this->call('PUT', "/depots/{$start['id']}/morceaux/0", [], [], [], [], $bytes)->assertOk();
        $this->postJson("/depots/{$start['id']}/terminer", ['checksum' => hash('sha256', $bytes), 'group_id' => $group->id])
            ->assertForbidden();

        $this->assertDatabaseCount('media', 0);
    }

    public function test_sharing_and_removing_are_announced_to_the_group(): void
    {
        Event::fake([MediaAdded::class, MediaRemoved::class]);
        $group = Group::factory()->create();
        $media = Media::factory()->for($group->owner)->create();
        Storage::disk('local')->put($media->disk_path, 'octets');

        $this->actingAs($group->owner)->post("/groupes/{$group->id}/fichiers", ['media_id' => $media->id]);
        Event::assertDispatched(MediaAdded::class);

        $this->actingAs($group->owner)->delete("/fichiers/{$media->id}");
        Event::assertDispatched(MediaRemoved::class, fn (MediaRemoved $event) => $event->groupIds === [$group->id]
            && $event->name === $media->original_name);
    }

    public function test_only_members_may_listen_to_a_group_channel(): void
    {
        // Le broadcaster « null » des tests laisse tout passer : on prend celui de
        // Pusher avec des clés factices — la signature se calcule en local, rien ne part.
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'cle-test',
            'broadcasting.connections.pusher.secret' => 'secret-test',
            'broadcasting.connections.pusher.app_id' => 'app-test',
        ]);

        // Les canaux se sont enregistrés au démarrage sur le driver d'alors : on
        // les redéclare sur celui de Pusher.
        require base_path('routes/channels.php');

        $group = Group::factory()->create();
        $member = User::factory()->create();
        $group->addMember($member);

        $this->actingAs($member)
            ->post('/broadcasting/auth', ['channel_name' => "private-groups.{$group->id}", 'socket_id' => '1234.5678'])
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->post('/broadcasting/auth', ['channel_name' => "private-groups.{$group->id}", 'socket_id' => '1234.5678'])
            ->assertForbidden();

        $this->actingAs($member)
            ->post('/broadcasting/auth', ['channel_name' => "private-users.{$member->id}", 'socket_id' => '1234.5678'])
            ->assertOk();
        $this->actingAs($member)
            ->post('/broadcasting/auth', ['channel_name' => "private-users.{$group->owner_id}", 'socket_id' => '1234.5678'])
            ->assertForbidden();
    }

    public function test_the_browser_gets_the_broadcast_settings(): void
    {
        config(['broadcasting.default' => 'pusher', 'broadcasting.client.key' => 'cle-test']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('broadcast.key', 'cle-test')
                ->where('broadcast.user_id', $user->id));
    }
}
