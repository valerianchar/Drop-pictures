<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_screen_renders(): void
    {
        $this->get('/connexion')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }

    public function test_visitors_are_sent_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect('/connexion');
    }

    public function test_a_user_can_sign_in(): void
    {
        $user = User::factory()->create();

        $this->post('/connexion', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_missing_credentials_show_the_single_inline_message(): void
    {
        $this->post('/connexion', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'Saisis ton e-mail et ton mot de passe.']);

        $this->assertGuest();
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $user = User::factory()->create();

        $this->post('/connexion', ['email' => $user->email, 'password' => 'pas-le-bon'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_user_can_sign_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/deconnexion')
            ->assertRedirect('/connexion');

        $this->assertGuest();
    }

    public function test_signed_in_users_skip_the_login_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/connexion')
            ->assertRedirect('/');
    }
}
