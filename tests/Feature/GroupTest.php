<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_creates_a_group_and_is_its_first_member(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/')
            ->post('/groupes', ['name' => 'Week-end à Annecy'])
            ->assertRedirect('/')
            ->assertSessionHas('success');

        $group = Group::query()->firstOrFail();
        $this->assertTrue($group->hasMember($user));
        $this->assertSame(32, strlen($group->invite_token));

        $this->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('groups', 1)
                ->where('groups.0.name', 'Week-end à Annecy')
                ->where('groups.0.members_label', '1 membre')
                ->where('groups.0.detail', 'Aucun fichier pour l’instant'));
    }

    public function test_a_member_shares_a_file_to_the_group_and_others_download_the_original(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::factory()->for($owner, 'owner')->create();
        $group->addMember($member);

        $bytes = random_bytes(1500);
        $media = Media::factory()->for($owner)->create(['checksum_sha256' => hash('sha256', $bytes)]);
        Storage::disk('local')->put($media->disk_path, $bytes);

        $this->actingAs($owner)
            ->post("/groupes/{$group->id}/fichiers", ['media_id' => $media->id])
            ->assertRedirect()
            ->assertSessionHas('success', "Envoyé au groupe « {$group->name} » en qualité d’origine.");

        $this->actingAs($member)->get("/groupes/{$group->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Groups/Show')
                ->has('media', 1)
                ->where('media.0.name', $media->original_name)
                ->where('media.0.can_edit', false)
                ->has('members', 2));

        $download = $this->actingAs($member)->get("/fichiers/{$media->id}/telecharger")->assertOk();
        $this->assertSame($bytes, file_get_contents($download->getFile()->getPathname()));
    }

    public function test_a_stranger_cannot_see_the_group_nor_share_someone_elses_file(): void
    {
        $group = Group::factory()->create();
        $stranger = User::factory()->create();
        $media = Media::factory()->create();

        $this->actingAs($stranger)->get("/groupes/{$group->id}")->assertForbidden();
        $this->actingAs($group->owner)->post("/groupes/{$group->id}/fichiers", ['media_id' => $media->id])
            ->assertSessionHasErrors('media_id');
    }

    public function test_a_member_can_leave_but_the_owner_deletes_instead(): void
    {
        $group = Group::factory()->create();
        $member = User::factory()->create();
        $group->addMember($member);

        $this->actingAs($group->owner)->post("/groupes/{$group->id}/quitter")->assertForbidden();
        $this->actingAs($member)->post("/groupes/{$group->id}/quitter")->assertRedirect('/');
        $this->assertFalse($group->hasMember($member));

        $this->actingAs($member)->delete("/groupes/{$group->id}")->assertForbidden();
        $this->actingAs($group->owner)->delete("/groupes/{$group->id}")->assertRedirect('/');
        $this->assertDatabaseCount('groups', 0);
    }

    public function test_the_owner_can_regenerate_the_invite_link(): void
    {
        $group = Group::factory()->create();
        $old = $group->invite_token;

        $this->actingAs($group->owner)->post("/groupes/{$group->id}/lien")->assertRedirect();

        $this->assertNotSame($old, $group->fresh()->invite_token);
        $this->get("/g/{$old}")->assertNotFound();
    }
}
