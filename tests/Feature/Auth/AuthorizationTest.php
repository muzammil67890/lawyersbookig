<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the *server* enforces role/account-status boundaries --
 * independent of any client-side hiding, JS, or localStorage state,
 * which none of these requests even send.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_every_protected_dashboard(): void
    {
        $this->get(route('dashboard.redirect'))->assertRedirect(route('login'));
        $this->get(route('customer.dashboard'))->assertRedirect(route('login'));
        $this->get(route('lawyer.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_customer_can_access_the_customer_dashboard(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->get(route('customer.dashboard'))->assertOk();
    }

    public function test_customer_cannot_access_the_lawyer_dashboard(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->get(route('lawyer.dashboard'))->assertForbidden();
    }

    public function test_customer_cannot_access_the_admin_dashboard(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_lawyer_cannot_access_the_admin_dashboard(): void
    {
        $lawyer = User::factory()->lawyer()->create(['email_verified_at' => now()]);

        $this->actingAs($lawyer)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_lawyer_cannot_access_the_customer_dashboard(): void
    {
        $lawyer = User::factory()->lawyer()->create(['email_verified_at' => now()]);

        $this->actingAs($lawyer)->get(route('customer.dashboard'))->assertForbidden();
    }

    public function test_admin_can_access_the_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_admin_cannot_access_the_customer_or_lawyer_dashboard(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('customer.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('lawyer.dashboard'))->assertForbidden();
    }

    public function test_suspended_users_cannot_reach_protected_routes_even_with_a_live_session(): void
    {
        $customer = User::factory()->suspended()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($customer)->get(route('dashboard.redirect'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_dashboard_redirect_sends_each_role_to_its_own_dashboard(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $lawyer = User::factory()->lawyer()->create(['email_verified_at' => now()]);
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->get(route('dashboard.redirect'))->assertRedirect(route('customer.dashboard'));
        $this->actingAs($lawyer)->get(route('dashboard.redirect'))->assertRedirect(route('lawyer.dashboard'));
        $this->actingAs($admin)->get(route('dashboard.redirect'))->assertRedirect(route('admin.dashboard'));
    }
}
