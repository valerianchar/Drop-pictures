<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_screen_renders(): void
    {
        $this->get('/mot-de-passe-oublie')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_a_reset_link_is_sent_to_a_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/mot-de-passe-oublie', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_email_gets_the_same_answer(): void
    {
        Notification::fake();

        $this->post('/mot-de-passe-oublie', ['email' => 'inconnu@exemple.fr'])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_the_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/mot-de-passe-oublie', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->get("/nouveau-mot-de-passe/{$notification->token}?email={$user->email}")
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Auth/ResetPassword')
                    ->where('email', $user->email));

            $this->post('/nouveau-mot-de-passe', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'nouveau-mdp',
                'password_confirmation' => 'nouveau-mdp',
            ])->assertRedirect('/');

            $this->assertTrue(Hash::check('nouveau-mdp', $user->fresh()->password));

            return true;
        });
    }
}
