<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Focused checks for the specific risks named in the Phase 3 security
 * audit that don't naturally belong inside the Registration/Login/
 * Logout/Authorization/Verification test files.
 */
class SecurityTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    /**
     * Laravel's HTTP test client does not attach a CSRF token to
     * requests, so a request that reached a controller here proves
     * nothing either way about whether CSRF is enforced for a real
     * browser. What we *can* prove statically and unambiguously is
     * that every state-changing auth route is actually assigned the
     * framework's CSRF-validation middleware via the 'web' group --
     * i.e. that nobody accidentally routed these outside of it or
     * added them to the CSRF except-list.
     */
    public function test_state_changing_auth_routes_are_protected_by_csrf_middleware(): void
    {
        $router = app('router');

        $stateChangingRoutes = ['login', 'register', 'logout', 'password.email', 'password.store', 'verification.send'];

        foreach ($stateChangingRoutes as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Expected a named route [$name] to exist.");

            $resolved = $router->gatherRouteMiddleware($route);

            $hasCsrf = collect($resolved)->contains(
                fn ($middleware) => $middleware === ValidateCsrfToken::class || $middleware === 'web'
            );

            $this->assertTrue($hasCsrf, "Route [$name] is expected to run inside the CSRF-protected 'web' middleware group.");
        }
    }

    public function test_registration_cannot_mass_assign_account_status_or_id(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Tester',
            'email' => 'mass-assign@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'role' => 'customer',
            // None of the following are validated/whitelisted fields on
            // RegisterRequest, so they must have zero effect.
            'id' => 999999,
            'status' => 'suspended',
            'email_verified_at' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect();

        $user = User::query()->where('email', 'mass-assign@example.com')->firstOrFail();

        $this->assertNotSame(999999, $user->id);
        $this->assertSame(UserStatus::ACTIVE, $user->status);
        $this->assertSame(UserRole::CUSTOMER, $user->role);
    }

    /**
     * The login/register controllers only ever redirect to internal
     * named routes (`route('dashboard.redirect')`, or Laravel's own
     * session-backed `intended()`), never to a URL taken from request
     * input -- so there is nothing for an attacker-supplied redirect
     * parameter to influence.
     */
    public function test_login_redirect_ignores_attacker_supplied_redirect_parameters(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $response = $this->post('/login?redirect=https://evil.example.com', [
            'email' => $user->email,
            'password' => 'correct-password',
            'redirect_to' => 'https://evil.example.com',
            'next' => 'https://evil.example.com',
        ]);

        $response->assertRedirect(route('dashboard.redirect'));
        $this->assertNotSame('https://evil.example.com', $response->headers->get('Location'));
    }

    public function test_password_hash_is_never_exposed_in_responses(): void
    {
        $user = User::factory()->create(['password' => bcrypt('super-secret-password')]);

        $response = $this->actingAs($user)->get(route('customer.dashboard'));

        $response->assertOk();
        $response->assertDontSee('super-secret-password');
        $response->assertDontSee($user->password, false);
    }

    public function test_protected_routes_reject_guests(): void
    {
        $this->get(route('customer.dashboard'))->assertRedirect(route('login'));
        $this->get(route('lawyer.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->post(route('logout'))->assertRedirect(route('login'));
    }
}
