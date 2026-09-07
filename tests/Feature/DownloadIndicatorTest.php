<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Media;
use App\Models\MediaDownload;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * La pastille « déjà récupéré ».
 *
 * Elle est personnelle : deux membres d'un groupe ne voient pas la même chose
 * sur les mêmes fichiers. Et elle vaut pour les trois chemins de sortie — un
 * téléchargement, un ZIP, et la photothèque, que seul le navigateur peut
 * confirmer puisqu'iOS y dépose sans passer par le serveur.
 */
class DownloadIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloading_a_file_marks_it_in_the_gallery(): void
    {
        $user = User::factory()->create();
        $media = $this->fileFor($user, 'photo.jpg');

        $this->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('media.0.download', null));

        $this->actingAs($user)->get("/fichiers/{$media->id}/telecharger")->assertOk();

        $this->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('media.0.download.via', 'fichier')
                ->where('media.0.download.label', 'Téléchargé')
                ->where('media.0.download.times', 1));
    }

    public function test_the_inline_view_counts_as_the_photo_library(): void
    {
        $user = User::factory()->create();
        $media = $this->fileFor($user, 'photo.jpg');

        $this->actingAs($user)->get("/fichiers/{$media->id}/voir")->assertOk();

        $this->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('media.0.download.label', 'Dans Photos'));
    }

    public function test_a_zip_marks_every_file_it_carries(): void
    {
        $user = User::factory()->create();
        $first = $this->fileFor($user, 'a.jpg');
        $second = $this->fileFor($user, 'b.jpg');

        $this->actingAs($user)->get("/fichiers/telecharger?ids[]={$first->id}&ids[]={$second->id}")->assertOk();

        $this->assertSame(2, MediaDownload::query()->where('user_id', $user->id)->where('via', 'zip')->count());
    }

    public function test_the_browser_confirms_what_the_share_sheet_accepted(): void
    {
        $user = User::factory()->create();
        $media = $this->fileFor($user, 'photo.jpg');

        $this->actingAs($user)->postJson('/fichiers/enregistres', ['ids' => [$media->id]])->assertNoContent();

        $entry = MediaDownload::query()->firstOrFail();
        $this->assertSame('photos', $entry->via->value);
        $this->assertSame(1, $entry->times);

        // Deux fois de suite : la même ligne, un compteur de plus.
        $this->actingAs($user)->postJson('/fichiers/enregistres', ['ids' => [$media->id]])->assertNoContent();

        $this->assertSame(1, MediaDownload::query()->count());
        $this->assertSame(2, $entry->fresh()->times);
    }

    public function test_the_indicator_is_personal_to_each_member(): void
    {
        $group = Group::factory()->create();
        $member = User::factory()->create();
        $group->addMember($member);
        $media = $this->fileFor($group->owner, 'photo.jpg');
        $group->media()->attach($media->id, ['shared_by' => $group->owner_id]);

        $this->actingAs($member)->postJson('/fichiers/enregistres', ['ids' => [$media->id]])->assertNoContent();

        // Le membre voit sa pastille…
        $this->actingAs($member)->get("/groupes/{$group->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('media.0.download.label', 'Dans Photos'));

        // …le déposant, qui n'a rien récupéré, n'en voit aucune.
        $this->actingAs($group->owner)->get("/groupes/{$group->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('media.0.download', null));
    }

    public function test_a_stranger_cannot_mark_a_file(): void
    {
        $media = Media::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson('/fichiers/enregistres', ['ids' => [$media->id]])
            ->assertForbidden();

        $this->assertSame(0, MediaDownload::query()->count());
    }

    public function test_the_ids_are_checked(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/fichiers/enregistres', ['ids' => []])
            ->assertUnprocessable();
    }

    public function test_an_anonymous_visitor_leaves_no_trace(): void
    {
        $media = Media::factory()->create();
        Storage::disk('local')->put($media->disk_path, 'octets');
        $link = ShareLink::factory()->for($media)->for($media->user)->create();

        $this->get("/p/{$link->token}/telecharger")->assertOk();

        $this->assertSame(0, MediaDownload::query()->count());
    }

    private function fileFor(User $user, string $name): Media
    {
        $media = Media::factory()->for($user)->named($name)->create();
        Storage::disk('local')->put($media->disk_path, random_bytes(64));

        return $media;
    }
}
