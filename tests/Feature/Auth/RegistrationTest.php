<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_screen_renders(): void
    {
        $this->get('/inscription')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Register'));
    }

    public function test_a_visitor_can_create_an_account(): void
    {
        $this->post('/inscription', [
            'name' => 'Marie Dupont',
            'email' => 'marie@exemple.fr',
            'password' => 'motdepasse',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'marie@exemple.fr']);
    }

    public function test_an_existing_email_is_refused(): void
    {
        User::factory()->create(['email' => 'marie@exemple.fr']);

        $this->post('/inscription', [
            'name' => 'Marie',
            'email' => 'marie@exemple.fr',
            'password' => 'motdepasse',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_can_be_closed(): void
    {
        config(['drop.registration_open' => false]);

        $this->get('/inscription')->assertNotFound();
        $this->post('/inscription', ['name' => 'X', 'email' => 'x@exemple.fr', 'password' => 'motdepasse'])
            ->assertNotFound();
    }
}
