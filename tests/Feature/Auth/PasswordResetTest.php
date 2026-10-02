<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    /**
     * The response (and the session flash message it produces) must be
     * identical whether or not the submitted email belongs to a real
     * account -- otherwise the endpoint becomes an email-enumeration
     * oracle.
     */
    public function test_forgot_password_response_does_not_reveal_whether_the_account_exists(): void
    {
        Notification::fake();

        $existing = User::factory()->create();

        $responseForExisting = $this->post(route('password.email'), ['email' => $existing->email]);
        $responseForExisting->assertSessionHas('status');
        $statusForExisting = session('status');

        $responseForUnknown = $this->post(route('password.email'), ['email' => 'no-such-account@example.com']);
        $responseForUnknown->assertSessionHas('status');
        $statusForUnknown = session('status');

        $this->assertSame($statusForExisting, $statusForUnknown);

        // Only the real account actually receives a mail.
        Notification::assertSentTo($existing, \Illuminate\Auth\Notifications\ResetPassword::class);
    }

    public function test_a_valid_reset_token_changes_the_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('old-password')]);
        $token = Password::createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $response->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('a-brand-new-password', $user->password));
        $this->assertFalse(Hash::check('old-password', $user->password));
    }

    public function test_an_invalid_token_does_not_change_the_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('old-password')]);

        $response = $this->from(route('password.reset', ['token' => 'bogus']))->post(route('password.store'), [
            'token' => 'this-token-does-not-exist',
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $response->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertTrue(Hash::check('old-password', $user->password));
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $this->get(route('password.reset', ['token' => 'any-token']))->assertOk();
    }

    public function test_password_reset_requires_confirmation_and_minimum_strength(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
