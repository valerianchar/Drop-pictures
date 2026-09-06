<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ShareLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_permanent_link_can_be_created_and_serves_the_original_bytes(): void
    {
        $user = User::factory()->create();
        $bytes = random_bytes(2048);
        $media = Media::factory()->for($user)->create(['checksum_sha256' => hash('sha256', $bytes), 'size_bytes' => 2048]);
        Storage::disk('local')->put($media->disk_path, $bytes);

        $this->actingAs($user)
            ->post("/fichiers/{$media->id}/liens", ['limited' => false])
            ->assertRedirect()
            ->assertSessionHas('share_link');

        $link = ShareLink::query()->firstOrFail();
        $this->assertNull($link->expires_at);

        // Sans compte : la page publique, puis le fichier, tel quel.
        $this->get("/p/{$link->token}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Share/Show')
                ->where('media.name', $media->original_name)
                ->where('media.checksum', $media->checksum_sha256));

        $download = $this->get("/p/{$link->token}/telecharger")
            ->assertOk()
            ->assertDownload($media->original_name);

        $this->assertSame($bytes, file_get_contents($download->getFile()->getPathname()));
        $this->assertSame(1, $link->fresh()->downloads_count);
    }

    public function test_a_limited_link_expires_after_the_configured_days(): void
    {
        config(['drop.share_link_days' => 7]);
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create();

        $this->actingAs($user)->post("/fichiers/{$media->id}/liens", ['limited' => true]);

        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, ShareLink::query()->firstOrFail()->expires_at->timestamp, 5);
    }

    public function test_an_expired_link_is_not_found(): void
    {
        $link = ShareLink::factory()->expired()->create();

        $this->get("/p/{$link->token}")->assertNotFound();
        $this->get("/p/{$link->token}/telecharger")->assertNotFound();
    }

    public function test_only_the_owner_can_share_or_revoke(): void
    {
        $media = Media::factory()->create();
        $link = ShareLink::factory()->for($media)->for($media->user)->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post("/fichiers/{$media->id}/liens")->assertForbidden();
        $this->actingAs($stranger)->delete("/liens/{$link->id}")->assertForbidden();

        $this->actingAs($media->user)->delete("/liens/{$link->id}")->assertRedirect();
        $this->assertDatabaseCount('share_links', 0);
    }
}
