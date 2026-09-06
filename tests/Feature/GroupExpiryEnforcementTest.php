<?php

namespace Tests\Feature;

use App\Actions\ExpireGroup;
use App\Models\Group;
use App\Models\Media;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Un groupe arrivé à échéance est fermé à l'instant même, pas au prochain
 * passage du planificateur.
 */
class GroupExpiryEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expired_group_refuses_everything_until_it_is_purged(): void
    {
        $group = Group::factory()->expiring(now()->subMinute())->create();
        $member = User::factory()->create();
        $group->addMember($member);
        $media = Media::factory()->for($member)->create();
        Storage::disk('local')->put($media->disk_path, 'x');
        $group->media()->attach($media->id, ['shared_by' => $member->id]);

        $this->actingAs($member)->get("/groupes/{$group->id}")->assertForbidden();
        $this->actingAs($member)->get("/groupes/{$group->id}/telecharger")->assertForbidden();
        $this->actingAs($member)->post("/groupes/{$group->id}/fichiers", ['media_id' => $media->id])->assertForbidden();
        $this->actingAs($member)->post("/groupes/{$group->id}/invitations", ['email' => 'x@exemple.fr'])->assertForbidden();

        // Le lien d'invitation ne mène plus nulle part, connecté ou non.
        $this->actingAs(User::factory()->create())->get("/g/{$group->invite_token}")->assertNotFound();
        $this->post('/deconnexion');
        $this->get("/g/{$group->invite_token}")->assertNotFound();

        // Le dépôt direct est refusé — le fichier ne sera pas détruit dans l'heure.
        $bytes = random_bytes(50);
        $start = $this->actingAs($member)->postJson('/depots', ['name' => 'tard.jpg', 'size' => 50])->json();
        $this->call('PUT', "/depots/{$start['id']}/morceaux/0", [], [], [], [], $bytes)->assertOk();
        $this->postJson("/depots/{$start['id']}/terminer", ['checksum' => hash('sha256', $bytes), 'group_id' => $group->id])->assertForbidden();

        // Plus dans la liste de l'accueil.
        $this->actingAs($member)->get('/')->assertInertia(fn (AssertableInertia $page) => $page->has('groups', 0));
    }

    public function test_the_group_channel_closes_with_the_group(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'cle-test',
            'broadcasting.connections.pusher.secret' => 'secret-test',
            'broadcasting.connections.pusher.app_id' => 'app-test',
        ]);
        require base_path('routes/channels.php');

        $group = Group::factory()->expiring(now()->subMinute())->create();

        $this->actingAs($group->owner)
            ->post('/broadcasting/auth', ['channel_name' => "private-groups.{$group->id}", 'socket_id' => '1234.5678'])
            ->assertForbidden();
    }

    public function test_a_pending_invitation_to_an_expired_group_is_dropped_at_registration(): void
    {
        $group = Group::factory()->expiring(now()->addMinute())->create();

        $this->get("/g/{$group->invite_token}")->assertRedirect('/inscription');
        $group->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->post('/inscription', ['name' => 'Léo', 'email' => 'leo@exemple.fr', 'password' => 'motdepasse'])
            ->assertRedirect('/');

        $this->assertFalse($group->hasMember(User::query()->where('email', 'leo@exemple.fr')->firstOrFail()));
    }

    public function test_expiring_keeps_a_dropped_file_that_still_lives_elsewhere(): void
    {
        $owner = User::factory()->create();
        $dying = Group::factory()->for($owner, 'owner')->expiring(now()->subMinute())->create();
        $other = Group::factory()->for($owner, 'owner')->create();

        $onlyHere = Media::factory()->for($owner)->create(['original_name' => 'seul.jpg']);
        $alsoElsewhere = Media::factory()->for($owner)->create(['original_name' => 'ailleurs.jpg']);
        $linked = Media::factory()->for($owner)->create(['original_name' => 'lie.jpg']);
        foreach ([$onlyHere, $alsoElsewhere, $linked] as $media) {
            Storage::disk('local')->put($media->disk_path, 'x');
            $dying->media()->attach($media->id, ['shared_by' => $owner->id, 'uploaded_here' => true]);
        }
        $other->media()->attach($alsoElsewhere->id, ['shared_by' => $owner->id]);
        ShareLink::factory()->for($linked)->for($owner)->create();

        $destroyed = app(ExpireGroup::class)->handle($dying);

        $this->assertSame(1, $destroyed);
        $this->assertDatabaseMissing('media', ['id' => $onlyHere->id]);
        $this->assertDatabaseHas('media', ['id' => $alsoElsewhere->id]);
        $this->assertDatabaseHas('media', ['id' => $linked->id]);
        $this->assertTrue($other->media()->whereKey($alsoElsewhere->id)->exists());
    }
}
