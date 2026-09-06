<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * L'original affiché dans le navigateur — le chemin d'« Enregistrer dans
 * Photos » sur iPhone. Mêmes octets, seule la disposition change.
 */
class MediaViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_view_the_original_inline(): void
    {
        $user = User::factory()->create();
        $bytes = random_bytes(1200);
        $media = Media::factory()->for($user)->create([
            'original_name' => 'Été à Annecy.jpg',
            'checksum_sha256' => hash('sha256', $bytes),
        ]);
        Storage::disk('local')->put($media->disk_path, $bytes);

        $response = $this->actingAs($user)->get("/fichiers/{$media->id}/voir")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Checksum-SHA256', $media->checksum_sha256);

        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString("filename*=utf-8''%C3%89t%C3%A9%20%C3%A0%20Annecy.jpg", $response->headers->get('Content-Disposition'));
        $this->assertSame($bytes, file_get_contents($response->getFile()->getPathname()));
    }

    public function test_a_stranger_cannot_view_it(): void
    {
        $media = Media::factory()->create();
        Storage::disk('local')->put($media->disk_path, 'octets');

        $this->actingAs(User::factory()->create())->get("/fichiers/{$media->id}/voir")->assertForbidden();
    }

    public function test_the_gallery_exposes_the_inline_url_and_the_mime_type(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create();

        $this->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('media.0.view_url', route('media.view', $media->id))
                ->where('media.0.mime_type', 'image/jpeg'));
    }

    public function test_a_share_link_serves_the_original_inline_and_counts_it(): void
    {
        $bytes = random_bytes(900);
        $media = Media::factory()->video()->create(['checksum_sha256' => hash('sha256', $bytes)]);
        Storage::disk('local')->put($media->disk_path, $bytes);
        $link = ShareLink::factory()->for($media)->for($media->user)->create();

        $this->get("/p/{$link->token}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('media.view_url', route('share.view', $link->token)));

        $response = $this->get("/p/{$link->token}/voir")
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4');

        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertSame($bytes, file_get_contents($response->getFile()->getPathname()));
        $this->assertSame(1, $link->fresh()->downloads_count);
    }

    public function test_an_expired_link_hides_the_inline_view_too(): void
    {
        $link = ShareLink::factory()->expired()->create();

        $this->get("/p/{$link->token}/voir")->assertNotFound();
    }
}
