<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use App\Notifications\LawyerApplicationApproved;
use App\Notifications\LawyerApplicationRejected;

class SecurityTest extends NotificationTestCase
{
    public function test_user_cannot_mark_another_users_notification_read_or_unread(): void
    {
        $owner = User::factory()->lawyer()->create();
        $attacker = User::factory()->lawyer()->create();
        $n = $this->deliver($owner, new LawyerApplicationApproved());

        $this->actingAs($attacker)->post(route('notifications.read', $n->id))->assertNotFound();
        $this->assertNull($n->fresh()->read_at);

        $n->markAsRead();
        $this->actingAs($attacker)->post(route('notifications.unread', $n->id))->assertNotFound();
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_admin_has_no_access_to_other_users_notifications(): void
    {
        $owner = User::factory()->lawyer()->create();
        $admin = User::factory()->admin()->create();
        $n = $this->deliver($owner, new LawyerApplicationApproved());

        $this->actingAs($admin)->post(route('notifications.read', $n->id))->assertNotFound();
        $this->assertNull($n->fresh()->read_at);
    }

    public function test_request_input_cannot_change_ownership(): void
    {
        $me = User::factory()->lawyer()->create();
        $victim = User::factory()->lawyer()->create();
        $mine = $this->deliver($me, new LawyerApplicationApproved());
        $theirs = $this->deliver($victim, new LawyerApplicationApproved());

        $this->actingAs($me)->post(route('notifications.read-all'), ['user_id' => $victim->id, 'notifiable_id' => $victim->id]);

        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
    }

    /**
     * The HTTP test client is exempt from CSRF verification, so (as in
     * Reviews\SecurityTest) assert the real guarantee: every mutating
     * notification route is POST-only and runs in the 'web' group.
     */
    public function test_mutating_routes_are_post_only_and_csrf_protected(): void
    {
        $router = app('router');

        foreach (['notifications.read', 'notifications.unread', 'notifications.read-all'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Expected route [$name].");
            $this->assertSame(['POST'], $route->methods(), "[$name] must be POST only.");

            $hasCsrf = collect($router->gatherRouteMiddleware($route))->contains(
                fn ($m) => $m === ValidateCsrfToken::class || $m === 'web'
            );
            $this->assertTrue($hasCsrf, "[$name] must run inside the CSRF-protected 'web' group.");
            $this->assertContains('auth', $route->gatherMiddleware());
        }
    }

    public function test_notification_rows_contain_csrf_tokens_and_no_get_mutations(): void
    {
        $user = User::factory()->lawyer()->create();
        $n = $this->deliver($user, new LawyerApplicationApproved());

        $this->actingAs($user)->get(route('notifications.index'))->assertSee('name="_token"', false);
        $this->actingAs($user)->get('/notifications/'.$n->id.'/read')->assertStatus(405);
    }

    public function test_payload_is_escaped_against_stored_xss(): void
    {
        $user = User::factory()->lawyer()->create();
        $payload = '<script>alert(1)</script><img src=x onerror=alert(2)>';
        $this->deliver($user, new LawyerApplicationRejected($payload));

        $response = $this->actingAs($user)->get(route('notifications.index'))->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertDontSee('<img src=x', false);
    }

    public function test_malicious_payload_cannot_inject_a_destination_link(): void
    {
        $user = User::factory()->create();
        $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'x',
            'data' => ['type' => 'appointment_confirmed', 'message' => 'Hi', 'appointment_id' => 'javascript:alert(1)', 'url' => 'https://evil.example'],
        ]);

        $response = $this->actingAs($user)->get(route('notifications.index'))->assertOk();

        $response->assertDontSee('javascript:', false);
        $response->assertDontSee('evil.example', false);
    }

    public function test_unauthorized_destination_still_enforces_its_own_policy(): void
    {
        [$lawyerUser, $profile] = $this->lawyerWithProfile();
        $customer = User::factory()->create();
        $stranger = User::factory()->create();
        [$appointment] = $this->appointmentNotification($customer, $lawyerUser, $profile);

        // Even if a stranger were somehow handed the link, the page refuses.
        $this->actingAs($stranger)->get(route('customer.appointments.show', $appointment))->assertForbidden();
    }
}
