<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession([]);
        $sessionIdBeforeLogout = session()->getId();

        $this->actingAs($user)->post(route('logout'));

        $this->assertNotSame($sessionIdBeforeLogout, session()->getId());
        $this->assertGuest();
    }

    public function test_logout_is_not_reachable_via_get(): void
    {
        $user = User::factory()->create();

        // Logout must never be a bare, CSRF-exempt GET link.
        $response = $this->actingAs($user)->get(route('logout'));

        $response->assertMethodNotAllowed();
    }

    public function test_a_guest_cannot_log_out(): void
    {
        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
    }
}
