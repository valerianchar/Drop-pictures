<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GroupLifetimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_group_is_created_with_a_lifetime(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/')->post('/groupes', ['name' => 'Mariage', 'lifetime' => '7j'])->assertRedirect('/');
        $this->actingAs($user)->from('/')->post('/groupes', ['name' => 'Famille', 'lifetime' => 'illimite'])->assertRedirect('/');

        $wedding = Group::query()->where('name', 'Mariage')->firstOrFail();
        $family = Group::query()->where('name', 'Famille')->firstOrFail();

        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $wedding->expires_at->timestamp, 5);
        $this->assertNull($family->expires_at);

        $this->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.expires_label', fn (string $label): bool => str_starts_with($label, 'Expire dans'))
                ->where('groups.1.expires_label', null)
                ->has('lifetimes', 4));
    }

    public function test_an_unknown_lifetime_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/groupes', ['name' => 'X', 'lifetime' => 'toujours'])
            ->assertSessionHasErrors('lifetime');
    }

    public function test_an_expired_group_destroys_what_was_dropped_in_it_and_keeps_the_rest(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::factory()->for($owner, 'owner')->expiring(now()->subMinute())->create();
        $group->addMember($member);

        $dropped = Media::factory()->for($member)->create(['original_name' => 'depose.jpg']);
        $shared = Media::factory()->for($owner)->create(['original_name' => 'partage.jpg']);
        Storage::disk('local')->put($dropped->disk_path, 'a');
        Storage::disk('local')->put($shared->disk_path, 'b');
        $group->media()->attach($dropped->id, ['shared_by' => $member->id, 'uploaded_here' => true]);
        $group->media()->attach($shared->id, ['shared_by' => $owner->id, 'uploaded_here' => false]);

        $alive = Group::factory()->expiring(now()->addDay())->create();

        $this->artisan('drop:expire-groups')
            ->expectsOutputToContain('1 groupe(s) clos, 1 fichier(s) détruit(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
        $this->assertDatabaseHas('groups', ['id' => $alive->id]);
        $this->assertDatabaseMissing('media', ['id' => $dropped->id]);
        Storage::disk('local')->assertMissing($dropped->disk_path);
        $this->assertDatabaseHas('media', ['id' => $shared->id]);
        Storage::disk('local')->assertExists($shared->disk_path);
        $this->assertDatabaseCount('group_media', 0);
    }
}
