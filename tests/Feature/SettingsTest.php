<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Limits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_first_account_administers_the_instance(): void
    {
        $this->post('/inscription', ['name' => 'Marie', 'email' => 'marie@exemple.fr', 'password' => 'motdepasse']);
        $this->post('/deconnexion');
        $this->post('/inscription', ['name' => 'Léo', 'email' => 'leo@exemple.fr', 'password' => 'motdepasse']);

        $this->assertTrue(User::query()->where('email', 'marie@exemple.fr')->firstOrFail()->is_admin);
        $this->assertFalse(User::query()->where('email', 'leo@exemple.fr')->firstOrFail()->is_admin);
    }

    public function test_the_admin_sees_the_limits_and_the_disk(): void
    {
        config(['drop.quota_bytes' => 100 * 1024 ** 3, 'drop.max_file_bytes' => 50 * 1024 ** 3]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/reglages')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Edit')
                ->where('limits.quota_gb', 100)
                ->where('limits.video_gb', 50)
                ->has('disk.reserve_label')
                ->where('auth.user.is_admin', true));
    }

    public function test_the_admin_changes_the_limits_and_uploads_obey_them(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put('/reglages', ['quota_gb' => 200, 'photo_gb' => 0.5, 'video_gb' => 40, 'autre_gb' => 1])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(200 * 1024 ** 3, Limits::quotaBytes());
        $this->assertSame(40 * 1024 ** 3, Limits::maxBytes());

        // Une photo de 600 Mo dépasse le demi-Go autorisé ; une vidéo de 30 Go passe.
        $this->actingAs($admin)->postJson('/depots', ['name' => 'grande.jpg', 'size' => 600 * 1024 ** 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('size');
        $this->actingAs($admin)->postJson('/depots', ['name' => 'film.mov', 'size' => 30 * 1024 ** 3])
            ->assertCreated();

        $this->actingAs($admin)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('upload.max_file_bytes', 40 * 1024 ** 3)
                ->where('storage.quota_label', '200 Go'));
    }

    public function test_a_regular_account_cannot_reach_the_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/reglages')->assertForbidden();
        $this->actingAs($user)->put('/reglages', ['quota_gb' => 1, 'photo_gb' => 1, 'video_gb' => 1, 'autre_gb' => 1])->assertForbidden();
    }

    public function test_the_values_are_checked(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put('/reglages', ['quota_gb' => 0, 'photo_gb' => 'beaucoup', 'video_gb' => 1, 'autre_gb' => 1])
            ->assertSessionHasErrors(['quota_gb', 'photo_gb']);
    }

    public function test_an_administrator_can_be_named_from_the_console(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->artisan('drop:admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);

        $this->artisan('drop:admin', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_admin);

        $this->artisan('drop:admin', ['email' => 'inconnu@exemple.fr'])->assertFailed();
    }
}
