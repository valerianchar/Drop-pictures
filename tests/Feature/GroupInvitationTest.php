<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\User;
use App\Notifications\GroupInvitationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GroupInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_in_user_joins_by_the_group_link(): void
    {
        $group = Group::factory()->create();
        $guest = User::factory()->create();

        $this->actingAs($guest)
            ->get("/g/{$group->invite_token}")
            ->assertRedirect("/groupes/{$group->id}")
            ->assertSessionHas('success', "Tu as rejoint « {$group->name} ».");

        $this->assertTrue($group->hasMember($guest));

        // Rejoindre deux fois ne crée qu'une appartenance.
        $this->actingAs($guest)->get("/g/{$group->invite_token}")->assertRedirect("/groupes/{$group->id}");
        $this->assertSame(2, $group->memberships()->count());
    }

    public function test_a_visitor_is_sent_to_register_and_lands_in_the_group(): void
    {
        $group = Group::factory()->named('Famille')->create();

        $this->get("/g/{$group->invite_token}")->assertRedirect('/inscription');

        $this->get('/inscription')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pending_group', 'Famille'));

        $this->post('/inscription', ['name' => 'Léo', 'email' => 'leo@exemple.fr', 'password' => 'motdepasse'])
            ->assertRedirect("/groupes/{$group->id}");

        $this->assertTrue($group->hasMember(User::query()->where('email', 'leo@exemple.fr')->firstOrFail()));
    }

    public function test_a_pending_invitation_keeps_registration_open_when_closed(): void
    {
        config(['drop.registration_open' => false]);
        $group = Group::factory()->create();

        $this->get("/g/{$group->invite_token}")->assertRedirect('/inscription');
        $this->get('/inscription')->assertOk();
    }

    public function test_a_visitor_with_an_account_can_sign_in_instead_and_still_join(): void
    {
        $group = Group::factory()->create();
        $existing = User::factory()->create();

        $this->get("/g/{$group->invite_token}");

        $this->post('/connexion', ['email' => $existing->email, 'password' => 'password'])
            ->assertRedirect("/groupes/{$group->id}");

        $this->assertTrue($group->hasMember($existing));
    }

    public function test_a_member_invites_by_email_and_the_invitee_joins_by_their_link(): void
    {
        Notification::fake();
        $group = Group::factory()->create();

        $this->actingAs($group->owner)
            ->post("/groupes/{$group->id}/invitations", ['email' => 'Leo@Exemple.fr'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Invitation envoyée à leo@exemple.fr.');

        $invitation = GroupInvitation::query()->firstOrFail();
        $this->assertSame('leo@exemple.fr', $invitation->email);

        Notification::assertSentOnDemand(GroupInvitationReceived::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'leo@exemple.fr');

        // Renvoyer à la même adresse rappelle l'invitation au lieu d'en créer une autre.
        $this->actingAs($group->owner)->post("/groupes/{$group->id}/invitations", ['email' => 'leo@exemple.fr']);
        $this->assertDatabaseCount('group_invitations', 1);

        // L'invité, lui, arrive sans session.
        $this->post('/deconnexion');
        $this->assertGuest();

        $this->get("/invitations/{$invitation->token}")->assertRedirect('/inscription');
        $this->post('/inscription', ['name' => 'Léo', 'email' => 'leo@exemple.fr', 'password' => 'motdepasse'])
            ->assertRedirect("/groupes/{$group->id}");

        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertTrue($group->hasMember(User::query()->where('email', 'leo@exemple.fr')->firstOrFail()));
    }

    public function test_a_used_invitation_link_no_longer_lets_anyone_in(): void
    {
        $invitation = GroupInvitation::factory()->create(['accepted_at' => now()]);

        $this->get("/invitations/{$invitation->token}")
            ->assertRedirect('/connexion')
            ->assertSessionHas('error');
    }

    public function test_a_stranger_cannot_invite(): void
    {
        $group = Group::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post("/groupes/{$group->id}/invitations", ['email' => 'x@exemple.fr'])
            ->assertForbidden();
    }
}
