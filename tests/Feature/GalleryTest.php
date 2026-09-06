<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_lists_the_users_files_with_their_quality_badge(): void
    {
        $user = User::factory()->create(['name' => 'Marie Dupont']);
        Media::factory()->for($user)->create(['original_name' => 'IMG_2041.jpg', 'width' => 6000, 'height' => 4000, 'size_bytes' => 14_890_000]);
        Media::factory()->for($user)->video()->create();
        Media::factory()->for($user)->rawFile()->create();
        Media::factory()->create(); // Quelqu'un d'autre.

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('media', 3)
                ->where('media.2.name', 'IMG_2041.jpg')
                ->where('media.2.quality', '24 Mpx')
                ->where('media.2.meta', '6000×4000 · 14 Mo')
                ->where('media.1.quality', '4K 60')
                ->where('media.1.is_video', true)
                ->where('media.1.duration_label', '2:41')
                ->where('media.0.quality', 'RAW')
                ->where('storage.count', 3)
                ->where('auth.user.first_name', 'Marie'));
    }

    public function test_the_gallery_filters_by_tag_type_and_search(): void
    {
        $user = User::factory()->create();
        $portraits = Tag::factory()->for($user)->named('Portraits')->create();
        $tagged = Media::factory()->for($user)->create(['original_name' => 'lena-portrait.jpg']);
        $tagged->tags()->attach($portraits);
        Media::factory()->for($user)->create(['original_name' => 'brouillard.jpg']);
        Media::factory()->for($user)->video()->create();

        $this->actingAs($user)->get('/?tag=Portraits')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('media', 1)
                ->where('media.0.name', 'lena-portrait.jpg')
                ->where('media.0.tags', ['Portraits'])
                ->where('tags', ['Portraits']));

        $this->actingAs($user)->get('/?type=video')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('media', 1)->where('media.0.is_video', true));

        $this->actingAs($user)->get('/?q=brouil')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('media', 1)->where('media.0.name', 'brouillard.jpg'));
    }

    public function test_tags_can_be_edited_and_orphans_disappear(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create();
        $old = Tag::factory()->for($user)->named('Vieux')->create();
        $media->tags()->attach($old);

        $this->actingAs($user)
            ->put("/fichiers/{$media->id}/tags", ['tags' => ['Nuit', 'Voyage']])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(['Nuit', 'Voyage'], $media->fresh()->tags->pluck('name')->all());
        $this->assertDatabaseMissing('tags', ['name' => 'Vieux']);
    }

    public function test_a_file_can_be_deleted_with_its_bytes(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create();
        Storage::disk('local')->put($media->disk_path, 'octets');

        $this->actingAs($user)->delete("/fichiers/{$media->id}")->assertRedirect('/');

        $this->assertDatabaseCount('media', 0);
        Storage::disk('local')->assertMissing($media->disk_path);
    }

    public function test_someone_elses_file_cannot_be_downloaded_or_deleted(): void
    {
        $media = Media::factory()->create();
        Storage::disk('local')->put($media->disk_path, 'octets');

        $this->actingAs(User::factory()->create())->get("/fichiers/{$media->id}/telecharger")->assertForbidden();
        $this->actingAs(User::factory()->create())->delete("/fichiers/{$media->id}")->assertForbidden();
    }
}
