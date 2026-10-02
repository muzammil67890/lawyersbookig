<?php

namespace Tests\Feature\Reviews;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\LawyerProfile;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9: review submission -- the central business rule that a
 * review may only be created for a genuinely COMPLETED appointment
 * belonging to the acting customer (requirements #3, #6, #8, #31).
 *
 * The raw model-level relationship correctness and the database-level
 * unique-constraint duplicate check already live in
 * tests/Feature/DomainSchemaTest.php (added in Phase 2, since the
 * `reviews` table existed before this phase used it) and are not
 * repeated here; this file covers the HTTP-level eligibility rules
 * instead (ReviewPolicy/ReviewController), including the
 * application-level 403 a duplicate attempt gets before it would ever
 * reach that database constraint.
 */
class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    /** A completed appointment between a fresh customer and a fresh approved lawyer. */
    protected function completedAppointment(): array
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $customer = User::factory()->create();
        $service = Service::factory()->create();

        $appointment = Appointment::factory()->completed()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
            'service_id' => $service->id,
        ]);

        return [$appointment, $customer, $lawyerUser, $lawyer];
    }

    public function test_eligible_customer_can_review_a_completed_appointment(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $response = $this->actingAs($customer)->post(
            route('customer.appointments.review.store', $appointment),
            ['rating' => 5, 'comment' => 'Excellent, thorough advice.']
        );

        $response->assertRedirect(route('customer.appointments.show', $appointment));

        $this->assertDatabaseHas('reviews', [
            'appointment_id' => $appointment->id,
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $appointment->lawyer_profile_id,
            'rating' => 5,
            'status' => 'published',
        ]);
    }

    public function test_a_review_can_be_submitted_with_no_comment(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 4])
            ->assertRedirect();

        $this->assertDatabaseHas('reviews', ['appointment_id' => $appointment->id, 'rating' => 4, 'comment' => null]);
    }

    public function test_guest_cannot_submit_a_review(): void
    {
        [$appointment] = $this->completedAppointment();

        $this->post(route('customer.appointments.review.store', $appointment), ['rating' => 5])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_customer_cannot_review_another_customers_appointment(): void
    {
        [$appointment] = $this->completedAppointment();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 5])
            ->assertForbidden();

        $this->assertDatabaseCount('reviews', 0);
    }

    /** @dataProvider nonCompletedStatuses */
    public function test_an_appointment_that_has_not_been_completed_cannot_be_reviewed(string $status): void
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $customer = User::factory()->create();

        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
            'status' => $status,
        ]);

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 5])
            ->assertForbidden();

        $this->assertDatabaseCount('reviews', 0);
    }

    public static function nonCompletedStatuses(): array
    {
        return [
            'pending' => [AppointmentStatus::PENDING->value],
            'confirmed' => [AppointmentStatus::CONFIRMED->value],
            'cancelled' => [AppointmentStatus::CANCELLED->value],
            'rejected' => [AppointmentStatus::REJECTED->value],
            'rescheduled' => [AppointmentStatus::RESCHEDULED->value],
        ];
    }

    public function test_a_completed_appointment_can_be_reviewed(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $this->actingAs($customer)
            ->get(route('customer.appointments.review.create', $appointment))
            ->assertOk();
    }

    public function test_the_same_appointment_cannot_be_reviewed_twice(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        Review::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $appointment->lawyer_profile_id,
            'appointment_id' => $appointment->id,
        ]);

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 3])
            ->assertForbidden();

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_the_leave_review_form_is_unreachable_once_a_review_exists(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        Review::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $appointment->lawyer_profile_id,
            'appointment_id' => $appointment->id,
        ]);

        $this->actingAs($customer)
            ->get(route('customer.appointments.review.create', $appointment))
            ->assertForbidden();
    }

    public function test_a_lawyer_cannot_review_an_appointment_using_the_customer_endpoint(): void
    {
        [$appointment, , $lawyerUser] = $this->completedAppointment();

        $this->actingAs($lawyerUser)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 5])
            ->assertForbidden();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_a_suspended_customer_cannot_submit_a_review_mid_session(): void
    {
        [$appointment, $customer] = $this->completedAppointment();
        $customer->update(['status' => 'suspended']);

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 5])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('reviews', 0);
    }

    // --- Rating validation ---------------------------------------------

    public function test_rating_of_one_is_accepted(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('reviews', ['appointment_id' => $appointment->id, 'rating' => 1]);
    }

    public function test_rating_of_five_is_accepted(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 5])
            ->assertRedirect();

        $this->assertDatabaseHas('reviews', ['appointment_id' => $appointment->id, 'rating' => 5]);
    }

    public function test_rating_of_zero_is_rejected(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 0])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_rating_of_six_is_rejected(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 6])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_a_negative_rating_is_rejected(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => -1])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_a_decimal_rating_is_rejected(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 4.5])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_a_missing_rating_is_rejected(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['comment' => 'Great!'])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_an_overly_long_comment_is_rejected(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), [
                'rating' => 5,
                'comment' => str_repeat('a', config('reviews.comment_max_length') + 1),
            ])
            ->assertSessionHasErrors('comment');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_a_whitespace_only_comment_is_treated_as_empty(): void
    {
        [$appointment, $customer] = $this->completedAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 5, 'comment' => '    '])
            ->assertRedirect();

        $this->assertDatabaseHas('reviews', ['appointment_id' => $appointment->id, 'comment' => null]);
    }

    // --- Spoofing --------------------------------------------------------

    public function test_a_spoofed_customer_id_in_the_request_is_ignored(): void
    {
        [$appointment, $customer] = $this->completedAppointment();
        $otherUser = User::factory()->create();

        $this->actingAs($customer)->post(route('customer.appointments.review.store', $appointment), [
            'rating' => 5,
            'customer_id' => $otherUser->id,
        ]);

        $review = Review::first();
        $this->assertSame($customer->id, $review->customer_id);
        $this->assertNotSame($otherUser->id, $review->customer_id);
    }

    public function test_a_spoofed_lawyer_profile_id_in_the_request_is_ignored(): void
    {
        [$appointment, $customer] = $this->completedAppointment();
        $otherLawyer = LawyerProfile::factory()->approved()->create();

        $this->actingAs($customer)->post(route('customer.appointments.review.store', $appointment), [
            'rating' => 5,
            'lawyer_profile_id' => $otherLawyer->id,
        ]);

        $review = Review::first();
        $this->assertSame($appointment->lawyer_profile_id, $review->lawyer_profile_id);
        $this->assertNotSame($otherLawyer->id, $review->lawyer_profile_id);
    }

    public function test_a_spoofed_appointment_id_in_the_payload_cannot_attach_the_review_elsewhere(): void
    {
        [$appointment, $customer] = $this->completedAppointment();
        [$otherAppointment] = $this->completedAppointment();

        $this->actingAs($customer)->post(route('customer.appointments.review.store', $appointment), [
            'rating' => 5,
            'appointment_id' => $otherAppointment->id,
        ]);

        $review = Review::first();
        $this->assertSame($appointment->id, $review->appointment_id);
        $this->assertNotSame($otherAppointment->id, $review->appointment_id);
    }

    // --- Self-review -----------------------------------------------------

    public function test_a_lawyer_cannot_review_their_own_completed_appointment(): void
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);

        // Contrived: force the appointment's customer_id to be the
        // lawyer's own user id, simulating a data-integrity edge case.
        // ReviewPolicy::create() must still refuse it via the explicit
        // "lawyer is not the acting user" guard, defense-in-depth on
        // top of the structural "only customers own appointments" rule.
        $appointment = Appointment::factory()->completed()->create([
            'customer_id' => $lawyerUser->id,
            'lawyer_profile_id' => $lawyer->id,
        ]);

        $this->actingAs($lawyerUser)
            ->post(route('customer.appointments.review.store', $appointment), ['rating' => 5])
            ->assertForbidden();

        $this->assertDatabaseCount('reviews', 0);
    }
}
