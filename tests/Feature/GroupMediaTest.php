<?php

namespace Tests\Feature;

use App\Events\MediaAdded;
use App\Events\MediaRemoved;
use App\Models\Group;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GroupMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_selection_is_shared_to_a_group_in_one_go(): void
    {
        Event::fake([MediaAdded::class]);
        $group = Group::factory()->create();
        $mine = Media::factory()->for($group->owner)->count(3)->create();

        $this->actingAs($group->owner)
            ->post("/groupes/{$group->id}/fichiers", ['media_ids' => $mine->pluck('id')->all()])
            ->assertRedirect()
            ->assertSessionHas('success', "3 fichiers envoyés au groupe « {$group->name} » en qualité d’origine.");

        $this->assertSame(3, $group->media()->count());
        Event::assertDispatchedTimes(MediaAdded::class, 3);

        // Repartager la même sélection n'ajoute rien et le dit.
        $this->actingAs($group->owner)
            ->post("/groupes/{$group->id}/fichiers", ['media_ids' => $mine->pluck('id')->all()])
            ->assertSessionHas('success', "Ces fichiers étaient déjà dans « {$group->name} ».");
    }

    public function test_someone_elses_file_in_the_selection_is_refused(): void
    {
        $group = Group::factory()->create();
        $mine = Media::factory()->for($group->owner)->create();
        $theirs = Media::factory()->create();

        $this->actingAs($group->owner)
            ->post("/groupes/{$group->id}/fichiers", ['media_ids' => [$mine->id, $theirs->id]])
            ->assertSessionHasErrors('media_ids.1');

        $this->assertSame(0, $group->media()->count());
    }

    public function test_removing_a_file_that_is_not_in_the_group_announces_nothing(): void
    {
        Event::fake([MediaRemoved::class]);
        $group = Group::factory()->create();
        $stranger = User::factory()->create();
        $media = Media::factory()->for($stranger)->create(['original_name' => 'Le groupe ferme, réinscrivez-vous ailleurs.jpg']);

        $this->actingAs($stranger)->delete("/groupes/{$group->id}/fichiers/{$media->id}")->assertNotFound();

        Event::assertNotDispatched(MediaRemoved::class);
    }

    public function test_the_picker_lists_my_files_not_yet_in_the_group(): void
    {
        $group = Group::factory()->create();
        $member = User::factory()->create();
        $group->addMember($member);

        $inGroup = Media::factory()->for($member)->create(['original_name' => 'deja.jpg']);
        $free = Media::factory()->for($member)->create(['original_name' => 'libre.jpg']);
        Media::factory()->for($group->owner)->create(['original_name' => 'pas-a-moi.jpg']);
        $group->media()->attach($inGroup->id, ['shared_by' => $member->id]);

        $this->actingAs($member)->getJson("/fichiers/choisir?exclude_group={$group->id}")
            ->assertOk()
            ->assertJsonCount(1, 'media')
            ->assertJsonPath('media.0.name', 'libre.jpg');

        $this->actingAs($member)->getJson("/fichiers/choisir?exclude_group={$group->id}&q=zzz")
            ->assertJsonCount(0, 'media');

        $this->assertNotNull($free);
    }
}
