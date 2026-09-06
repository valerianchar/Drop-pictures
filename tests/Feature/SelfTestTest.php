<?php

namespace Tests\Feature;

use App\Models\ShareLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SelfTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_self_test_passes_when_the_download_returns_the_deposited_bytes(): void
    {
        Queue::fake();

        // Le « serveur HTTP » renvoie ce qui est réellement sur le disque.
        Http::fake(function ($request) {
            preg_match('#/p/([^/]+)/telecharger#', $request->url(), $matches);
            $link = ShareLink::query()->where('token', $matches[1])->firstOrFail();

            return Http::response(Storage::disk('local')->get($link->media->disk_path));
        });

        $this->artisan('drop:selftest', ['--size' => 5000])
            ->assertSuccessful();

        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_self_test_fails_when_the_download_differs(): void
    {
        Queue::fake();
        Http::fake(fn () => Http::response('pas les bons octets'));

        $this->artisan('drop:selftest', ['--size' => 5000])->assertFailed();

        $this->assertDatabaseCount('media', 0);
    }
}
