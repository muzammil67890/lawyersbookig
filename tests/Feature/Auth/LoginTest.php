<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_a_customer_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $this->assertAuthenticatedAs($user);
        // Login hands off to the single centralized role router,
        // which then sends the user to their own dashboard.
        $response->assertRedirect(route('dashboard.redirect'));
        $this->get(route('dashboard.redirect'))->assertRedirect(route('customer.dashboard'));
    }

    public function test_a_lawyer_can_authenticate_and_is_redirected_to_the_lawyer_dashboard(): void
    {
        $user = User::factory()->lawyer()->create(['password' => bcrypt('correct-password')]);

        $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'correct-password']);

        $this->assertAuthenticatedAs($user);
        // Login hands off to the single centralized role router,
        // which then sends the user to their own dashboard.
        $response->assertRedirect(route('dashboard.redirect'));
        $this->get(route('dashboard.redirect'))->assertRedirect(route('lawyer.dashboard'));
    }

    public function test_an_admin_can_authenticate_and_is_redirected_to_the_admin_dashboard(): void
    {
        $user = User::factory()->admin()->create(['password' => bcrypt('correct-password')]);

        $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'correct-password']);

        $this->assertAuthenticatedAs($user);
        // Login hands off to the single centralized role router,
        // which then sends the user to their own dashboard.
        $response->assertRedirect(route('dashboard.redirect'));
        $this->get(route('dashboard.redirect'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_invalid_credentials_do_not_authenticate_the_user(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_error_does_not_reveal_whether_the_email_exists(): void
    {
        $existing = User::factory()->create(['password' => bcrypt('correct-password')]);

        $responseForExistingEmail = $this->from(route('login'))->post(route('login'), [
            'email' => $existing->email,
            'password' => 'wrong-password',
        ]);

        $existingEmailMessage = session()->get('errors')->first('email');

        $responseForUnknownEmail = $this->from(route('login'))->post(route('login'), [
            'email' => 'nobody-such-account@example.com',
            'password' => 'wrong-password',
        ]);

        $unknownEmailMessage = session()->get('errors')->first('email');

        $responseForExistingEmail->assertSessionHasErrors('email');
        $responseForUnknownEmail->assertSessionHasErrors('email');
        $this->assertSame($existingEmailMessage, $unknownEmailMessage);
    }

    public function test_suspended_account_cannot_authenticate(): void
    {
        $user = User::factory()->suspended()->create(['password' => bcrypt('correct-password')]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_session_is_regenerated_after_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        // Prime a session so it has an ID to compare against.
        $this->withSession([]);
        $originalSessionId = session()->getId();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'correct-password']);

        $this->assertNotSame($originalSessionId, session()->getId());
    }

    public function test_login_is_rate_limited_after_repeated_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);
        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        // The 6th attempt is blocked by the throttle before credentials
        // are even checked, even though the password below is correct.
        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
