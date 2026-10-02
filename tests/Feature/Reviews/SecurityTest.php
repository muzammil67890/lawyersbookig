<?php

namespace Tests\Feature\Reviews;

use App\Models\Appointment;
use App\Models\LawyerProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 9, requirement #23: IDOR, ownership and injection defenses
 * around viewing/editing/deleting a review. Mirrors
 * tests/Feature/Messaging/AuthorizationTest.php and
 * tests/Feature/Auth/SecurityTest.php's CSRF-route-membership check.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function review(): array
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $customer = User::factory()->create();
        $appointment = Appointment::factory()->completed()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
        ]);
        $review = Review::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
            'appointment_id' => $appointment->id,
            'rating' => 4,
            'comment' => 'Solid advice.',
        ]);

        return [$review, $customer, $lawyerUser, $lawyer];
    }

    public function test_owner_can_edit_their_own_review(): void
    {
        [$review, $customer] = $this->review();

        $this->actingAs($customer)
            ->get(route('customer.reviews.edit', $review))
            ->assertOk();

        $this->actingAs($customer)
            ->put(route('customer.reviews.update', $review), ['rating' => 2, 'comment' => 'Updated my mind.'])
            ->assertRedirect(route('customer.reviews.index'));

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 2, 'comment' => 'Updated my mind.']);
    }

    public function test_another_customer_cannot_view_someone_elses_review_edit_form(): void
    {
        [$review] = $this->review();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->get(route('customer.reviews.edit', $review))
            ->assertForbidden();
    }

    public function test_another_customer_cannot_update_someone_elses_review(): void
    {
        [$review] = $this->review();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->put(route('customer.reviews.update', $review), ['rating' => 1, 'comment' => 'Hijacked.'])
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 4, 'comment' => 'Solid advice.']);
    }

    public function test_the_reviewed_lawyer_cannot_edit_the_review_about_them(): void
    {
        [$review, , $lawyerUser] = $this->review();

        $this->actingAs($lawyerUser)
            ->put(route('customer.reviews.update', $review), ['rating' => 5])
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 4]);
    }

    public function test_editing_a_review_cannot_change_its_appointment_or_lawyer(): void
    {
        [$review, $customer] = $this->review();
        $otherLawyer = LawyerProfile::factory()->approved()->create();
        $otherAppointment = Appointment::factory()->completed()->create(['customer_id' => $customer->id]);

        $this->actingAs($customer)->put(route('customer.reviews.update', $review), [
            'rating' => 3,
            'lawyer_profile_id' => $otherLawyer->id,
            'appointment_id' => $otherAppointment->id,
        ]);

        $review->refresh();
        $this->assertNotSame($otherLawyer->id, $review->lawyer_profile_id);
        $this->assertNotSame($otherAppointment->id, $review->appointment_id);
    }

    public function test_owner_can_delete_their_own_review(): void
    {
        [$review, $customer] = $this->review();

        $this->actingAs($customer)
            ->delete(route('customer.reviews.destroy', $review))
            ->assertRedirect(route('customer.reviews.index'));

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_another_customer_cannot_delete_someone_elses_review(): void
    {
        [$review] = $this->review();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->delete(route('customer.reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_a_lawyer_cannot_delete_a_review_about_them(): void
    {
        [$review, , $lawyerUser] = $this->review();

        $this->actingAs($lawyerUser)
            ->delete(route('customer.reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_a_customers_review_index_never_shows_another_customers_review(): void
    {
        [, $customer] = $this->review();
        $otherCustomer = User::factory()->create();
        $otherLawyer = LawyerProfile::factory()->approved()->create();
        $otherAppointment = Appointment::factory()->completed()->create([
            'customer_id' => $otherCustomer->id,
            'lawyer_profile_id' => $otherLawyer->id,
        ]);
        Review::factory()->create([
            'customer_id' => $otherCustomer->id,
            'lawyer_profile_id' => $otherLawyer->id,
            'appointment_id' => $otherAppointment->id,
            'comment' => 'Only visible to its own author.',
        ]);

        $this->actingAs($customer)
            ->get(route('customer.reviews.index'))
            ->assertOk()
            ->assertDontSee('Only visible to its own author.');
    }

    public function test_review_comment_content_is_html_escaped_on_the_public_profile(): void
    {
        [$review, , , $lawyer] = $this->review();
        $review->update(['comment' => '<script>alert(1)</script>']);

        $response = $this->get(route('lawyers.show', $lawyer))->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_a_guest_cannot_view_a_review_edit_form(): void
    {
        [$review] = $this->review();

        $this->get(route('customer.reviews.edit', $review))->assertRedirect(route('login'));
    }

    /**
     * Laravel's HTTP test client does not attach a CSRF token to
     * requests, so a request that reached a controller in the tests
     * above proves nothing about CSRF on its own -- the framework
     * exempts unit tests from CSRF verification (see
     * ValidateCsrfToken::runningUnitTests()). What we *can* verify is
     * that these state-changing review routes run inside Laravel's
     * CSRF-protected 'web' middleware group, i.e. nobody accidentally
     * excluded them, mirroring tests/Feature/Auth/SecurityTest.php.
     */
    public function test_state_changing_review_routes_are_protected_by_csrf_middleware(): void
    {
        $router = app('router');

        $stateChangingRoutes = [
            'customer.appointments.review.store',
            'customer.reviews.update',
            'customer.reviews.destroy',
            'admin.reviews.reject',
            'admin.reviews.restore',
        ];

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
}
