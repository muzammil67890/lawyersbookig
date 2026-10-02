<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\LawyerAvailability;
use App\Models\LawyerProfile;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Phase 6 Part 2: complete appointment management -- lifecycle
 * transitions (confirm/reject/cancel/complete/reschedule), ownership,
 * IDOR protection, status-tampering resistance, and notification
 * dispatch. Booking itself (Phase 6 Part 1) is covered by
 * Customer\AppointmentBookingTest; slot-conflict math is covered by
 * Appointments\ConflictTest.
 */
class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function bookableLawyer(): array
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $service = Service::factory()->create();
        $lawyer->services()->attach($service->id);

        LawyerAvailability::factory()->create([
            'lawyer_profile_id' => $lawyer->id,
            'day_of_week' => null,
            'specific_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_recurring' => false,
            'is_available' => true,
        ]);

        LawyerAvailability::factory()->create([
            'lawyer_profile_id' => $lawyer->id,
            'day_of_week' => null,
            'specific_date' => now()->addDays(4)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_recurring' => false,
            'is_available' => true,
        ]);

        return [$lawyerUser, $lawyer, $service];
    }

    protected function pendingAppointment(): array
    {
        [$lawyerUser, $lawyer, $service] = $this->bookableLawyer();
        $customer = User::factory()->create();

        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
            'service_id' => $service->id,
            'appointment_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => AppointmentStatus::PENDING->value,
        ]);

        return [$appointment, $customer, $lawyerUser, $lawyer, $service];
    }

    protected function confirmedAppointment(): array
    {
        [$appointment, $customer, $lawyerUser, $lawyer, $service] = $this->pendingAppointment();
        $appointment->update(['status' => AppointmentStatus::CONFIRMED->value]);

        return [$appointment, $customer, $lawyerUser, $lawyer, $service];
    }

    // -- Viewing / IDOR -----------------------------------------------

    public function test_customer_can_view_their_own_appointment(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();

        $this->actingAs($customer)
            ->get(route('customer.appointments.show', $appointment))
            ->assertOk()
            ->assertSee($appointment->reference);
    }

    public function test_customer_cannot_view_another_customers_appointment(): void
    {
        [$appointment] = $this->pendingAppointment();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->get(route('customer.appointments.show', $appointment))
            ->assertForbidden();
    }

    public function test_lawyer_can_view_their_own_appointment(): void
    {
        [$appointment, , $lawyerUser] = $this->pendingAppointment();

        $this->actingAs($lawyerUser)
            ->get(route('lawyer.appointments.show', $appointment))
            ->assertOk()
            ->assertSee($appointment->reference);
    }

    public function test_lawyer_cannot_view_another_lawyers_appointment(): void
    {
        [$appointment] = $this->pendingAppointment();
        $otherLawyerUser = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->create(['user_id' => $otherLawyerUser->id]);

        $this->actingAs($otherLawyerUser)
            ->get(route('lawyer.appointments.show', $appointment))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$appointment] = $this->pendingAppointment();

        $this->get(route('customer.appointments.show', $appointment))->assertRedirect(route('login'));
    }

    public function test_customer_cannot_use_the_lawyer_route_for_their_own_appointment(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();

        $this->actingAs($customer)
            ->get(route('lawyer.appointments.show', $appointment))
            ->assertForbidden(); // role:lawyer middleware never lets a customer into /lawyer/* at all
    }

    public function test_a_lawyer_with_no_profile_yet_is_redirected_from_the_appointment_list(): void
    {
        $lawyerUser = User::factory()->lawyer()->create();

        $this->actingAs($lawyerUser)
            ->get(route('lawyer.appointments.index'))
            ->assertRedirect(route('lawyer.onboarding.create'));
    }

    // -- Confirm --------------------------------------------------------

    public function test_lawyer_can_confirm_a_pending_appointment(): void
    {
        Notification::fake();
        [$appointment, $customer, $lawyerUser] = $this->pendingAppointment();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.appointments.confirm', $appointment))
            ->assertRedirect(route('lawyer.appointments.show', $appointment));

        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->fresh()->status);
        Notification::assertSentTo($customer, AppointmentNotification::class);
    }

    public function test_customer_cannot_confirm_their_own_appointment(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();

        $this->actingAs($customer)
            ->post(route('lawyer.appointments.confirm', $appointment))
            ->assertForbidden(); // role:lawyer middleware blocks a customer from /lawyer/* entirely

        $this->assertSame(AppointmentStatus::PENDING, $appointment->fresh()->status);
    }

    public function test_a_lawyer_cannot_confirm_another_lawyers_appointment(): void
    {
        [$appointment] = $this->pendingAppointment();
        $otherLawyerUser = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->create(['user_id' => $otherLawyerUser->id]);

        $this->actingAs($otherLawyerUser)
            ->post(route('lawyer.appointments.confirm', $appointment))
            ->assertForbidden();

        $this->assertSame(AppointmentStatus::PENDING, $appointment->fresh()->status);
    }

    public function test_an_already_confirmed_appointment_cannot_be_confirmed_again(): void
    {
        [$appointment, , $lawyerUser] = $this->confirmedAppointment();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.appointments.confirm', $appointment))
            ->assertSessionHasErrors('appointment');

        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->fresh()->status);
    }

    public function test_a_rejected_appointment_cannot_be_confirmed(): void
    {
        [$appointment, , $lawyerUser] = $this->pendingAppointment();
        $appointment->update(['status' => AppointmentStatus::REJECTED->value]);

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.appointments.confirm', $appointment))
            ->assertSessionHasErrors('appointment');
    }

    // -- Reject -----------------------------------------------------------

    public function test_lawyer_can_reject_a_pending_appointment(): void
    {
        Notification::fake();
        [$appointment, $customer, $lawyerUser] = $this->pendingAppointment();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.appointments.reject', $appointment))
            ->assertRedirect(route('lawyer.appointments.show', $appointment));

        $this->assertSame(AppointmentStatus::REJECTED, $appointment->fresh()->status);
        Notification::assertSentTo($customer, AppointmentNotification::class);
    }

    public function test_a_confirmed_appointment_cannot_be_rejected(): void
    {
        [$appointment, , $lawyerUser] = $this->confirmedAppointment();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.appointments.reject', $appointment))
            ->assertSessionHasErrors('appointment');

        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->fresh()->status);
    }

    // -- Cancel -----------------------------------------------------------

    public function test_customer_can_cancel_a_pending_appointment(): void
    {
        Notification::fake();
        [$appointment, $customer, $lawyerUser] = $this->pendingAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.cancel', $appointment), ['reason' => 'Change of plans'])
            ->assertRedirect(route('customer.appointments.show', $appointment));

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::CANCELLED, $appointment->status);
        $this->assertSame('Change of plans', $appointment->cancellation_reason);
        Notification::assertSentTo($lawyerUser, AppointmentNotification::class);
    }

    public function test_customer_can_cancel_a_confirmed_appointment(): void
    {
        [$appointment, $customer] = $this->confirmedAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.cancel', $appointment))
            ->assertRedirect(route('customer.appointments.show', $appointment));

        $this->assertSame(AppointmentStatus::CANCELLED, $appointment->fresh()->status);
    }

    public function test_lawyer_cannot_cancel_a_pending_appointment_only_confirm_or_reject(): void
    {
        [$appointment, , $lawyerUser] = $this->pendingAppointment();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.appointments.cancel', $appointment))
            ->assertSessionHasErrors('appointment');

        $this->assertSame(AppointmentStatus::PENDING, $appointment->fresh()->status);
    }

    public function test_lawyer_can_cancel_a_confirmed_appointment(): void
    {
        [$appointment, $customer, $lawyerUser] = $this->confirmedAppointment();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.appointments.cancel', $appointment))
            ->assertRedirect(route('lawyer.appointments.show', $appointment));

        $this->assertSame(AppointmentStatus::CANCELLED, $appointment->fresh()->status);
    }

    public function test_a_rejected_appointment_cannot_be_cancelled(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();
        $appointment->update(['status' => AppointmentStatus::REJECTED->value]);

        $this->actingAs($customer)
            ->post(route('customer.appointments.cancel', $appointment))
            ->assertSessionHasErrors('appointment');
    }

    public function test_a_completed_appointment_cannot_be_cancelled(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();
        $appointment->update(['status' => AppointmentStatus::COMPLETED->value]);

        $this->actingAs($customer)
            ->post(route('customer.appointments.cancel', $appointment))
            ->assertSessionHasErrors('appointment');
    }

    public function test_customer_cannot_cancel_another_customers_appointment(): void
    {
        [$appointment] = $this->pendingAppointment();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->post(route('customer.appointments.cancel', $appointment))
            ->assertForbidden();

        $this->assertSame(AppointmentStatus::PENDING, $appointment->fresh()->status);
    }

    // -- Complete ----------------------------------------------------------

    public function test_lawyer_can_mark_a_started_confirmed_appointment_completed(): void
    {
        [$appointment, , $lawyerUser] = $this->confirmedAppointment();
        $appointment->update([
            'appointment_date' => now()->subDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
        ]);

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.appointments.complete', $appointment))
            ->assertRedirect(route('lawyer.appointments.show', $appointment));

        $this->assertSame(AppointmentStatus::COMPLETED, $appointment->fresh()->status);
    }

    public function test_a_future_confirmed_appointment_cannot_be_marked_completed_yet(): void
    {
        [$appointment, , $lawyerUser] = $this->confirmedAppointment();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.appointments.complete', $appointment))
            ->assertSessionHasErrors('appointment');

        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->fresh()->status);
    }

    // -- Reschedule ----------------------------------------------------------

    public function test_customer_can_reschedule_a_pending_appointment_to_a_free_slot(): void
    {
        Notification::fake();
        [$appointment, $customer, $lawyerUser] = $this->pendingAppointment();
        $newDate = now()->addDays(4)->toDateString();

        $response = $this->actingAs($customer)->post(route('customer.appointments.reschedule', $appointment), [
            'appointment_date' => $newDate,
            'start_time' => '11:00',
        ]);

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::RESCHEDULED, $appointment->status);

        $new = Appointment::where('rescheduled_from_id', $appointment->id)->first();
        $this->assertNotNull($new);
        $this->assertSame(AppointmentStatus::PENDING, $new->status);
        $this->assertSame($newDate, $new->appointment_date->toDateString());
        $this->assertSame('11:00:00', $new->start_time);
        $response->assertRedirect(route('customer.appointments.show', $new));
        Notification::assertSentTo($lawyerUser, AppointmentNotification::class);
    }

    public function test_lawyer_rescheduling_a_confirmed_appointment_keeps_it_confirmed(): void
    {
        [$appointment, $customer, $lawyerUser] = $this->confirmedAppointment();

        $this->actingAs($lawyerUser)->post(route('lawyer.appointments.reschedule', $appointment), [
            'appointment_date' => now()->addDays(4)->toDateString(),
            'start_time' => '13:00',
        ]);

        $new = Appointment::where('rescheduled_from_id', $appointment->id)->first();
        $this->assertNotNull($new);
        $this->assertSame(AppointmentStatus::CONFIRMED, $new->status);
    }

    public function test_rescheduling_into_an_already_booked_slot_is_rejected(): void
    {
        [$appointment, $customer, , $lawyer, $service] = $this->confirmedAppointment();

        // Another confirmed appointment already occupies 13:00 on day+4.
        Appointment::factory()->create([
            'customer_id' => User::factory()->create()->id,
            'lawyer_profile_id' => $lawyer->id,
            'service_id' => $service->id,
            'appointment_date' => now()->addDays(4)->toDateString(),
            'start_time' => '13:00:00',
            'end_time' => '13:30:00',
            'status' => AppointmentStatus::CONFIRMED->value,
        ]);

        $this->actingAs($customer)
            ->post(route('customer.appointments.reschedule', $appointment), [
                'appointment_date' => now()->addDays(4)->toDateString(),
                'start_time' => '13:00',
            ])
            ->assertSessionHasErrors('appointment');

        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->fresh()->status);
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_rescheduling_excludes_the_appointment_being_moved_from_its_own_conflict_check(): void
    {
        // Moving to the SAME date but a different time inside the same
        // slot-blocking appointment's own window must not conflict
        // with itself.
        [$appointment, $customer] = $this->pendingAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.reschedule', $appointment), [
                'appointment_date' => now()->addDays(3)->toDateString(),
                'start_time' => '14:00',
            ])
            ->assertRedirect();

        $this->assertSame(AppointmentStatus::RESCHEDULED, $appointment->fresh()->status);
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_rescheduling_to_the_exact_same_time_is_rejected(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.reschedule', $appointment), [
                'appointment_date' => $appointment->appointment_date->toDateString(),
                'start_time' => '10:00',
            ])
            ->assertSessionHasErrors('appointment');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_a_past_date_cannot_be_used_to_reschedule(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();

        $this->actingAs($customer)
            ->post(route('customer.appointments.reschedule', $appointment), [
                'appointment_date' => now()->subDay()->toDateString(),
                'start_time' => '10:00',
            ])
            ->assertSessionHasErrors('appointment_date');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_a_rejected_appointment_cannot_be_rescheduled(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();
        $appointment->update(['status' => AppointmentStatus::REJECTED->value]);

        $this->actingAs($customer)
            ->post(route('customer.appointments.reschedule', $appointment), [
                'appointment_date' => now()->addDays(4)->toDateString(),
                'start_time' => '11:00',
            ])
            ->assertSessionHasErrors('appointment');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_a_completed_appointment_cannot_be_rescheduled(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();
        $appointment->update(['status' => AppointmentStatus::COMPLETED->value]);

        $this->actingAs($customer)
            ->post(route('customer.appointments.reschedule', $appointment), [
                'appointment_date' => now()->addDays(4)->toDateString(),
                'start_time' => '11:00',
            ])
            ->assertSessionHasErrors('appointment');
    }

    public function test_another_customer_cannot_reschedule_someone_elses_appointment(): void
    {
        [$appointment] = $this->pendingAppointment();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->post(route('customer.appointments.reschedule', $appointment), [
                'appointment_date' => now()->addDays(4)->toDateString(),
                'start_time' => '11:00',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('appointments', 1);
    }

    // -- Status tampering ----------------------------------------------------

    public function test_status_cannot_be_set_directly_through_the_cancel_request(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();

        $this->actingAs($customer)->post(route('customer.appointments.cancel', $appointment), [
            'reason' => 'ignored',
            'status' => 'confirmed',
        ]);

        // Only "cancelled" is possible from this endpoint, regardless of
        // any "status" key smuggled into the request body.
        $this->assertSame(AppointmentStatus::CANCELLED, $appointment->fresh()->status);
    }

    public function test_reschedule_request_cannot_smuggle_a_status_or_ownership_change(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();
        $otherCustomer = User::factory()->create();

        $this->actingAs($customer)->post(route('customer.appointments.reschedule', $appointment), [
            'appointment_date' => now()->addDays(4)->toDateString(),
            'start_time' => '11:00',
            'status' => 'confirmed',
            'customer_id' => $otherCustomer->id,
        ]);

        $new = Appointment::where('rescheduled_from_id', $appointment->id)->first();
        $this->assertSame(AppointmentStatus::PENDING, $new->status);
        $this->assertSame($customer->id, $new->customer_id);
    }

    // -- Notification payload -------------------------------------------------

    public function test_notification_payload_carries_no_sensitive_data(): void
    {
        [$appointment, $customer, $lawyerUser] = $this->pendingAppointment();
        $appointment->update(['reason' => 'Confidential: divorce proceedings against a public figure']);

        $this->actingAs($lawyerUser)->post(route('lawyer.appointments.confirm', $appointment));

        $notification = $customer->notifications()->first();
        $this->assertNotNull($notification);

        $payload = json_encode($notification->data);
        $this->assertStringNotContainsString('Confidential', $payload);
        $this->assertStringNotContainsString($customer->email, $payload);
        $this->assertArrayHasKey('event', $notification->data);
        $this->assertArrayHasKey('reference', $notification->data);
    }

    // -- Filters / listing --------------------------------------------------

    public function test_customer_appointment_list_only_shows_their_own(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();
        [$otherAppointment] = $this->pendingAppointment();

        $response = $this->actingAs($customer)->get(route('customer.appointments.index'));

        $response->assertOk()->assertSee($appointment->reference);
        $response->assertDontSee($otherAppointment->reference);
    }

    public function test_lawyer_appointment_list_only_shows_their_own(): void
    {
        [$appointment, , $lawyerUser] = $this->pendingAppointment();
        [$otherAppointment] = $this->pendingAppointment();

        $response = $this->actingAs($lawyerUser)->get(route('lawyer.appointments.index'));

        $response->assertOk()->assertSee($appointment->reference);
        $response->assertDontSee($otherAppointment->reference);
    }

    public function test_status_filter_only_returns_matching_appointments(): void
    {
        [$appointment, $customer] = $this->pendingAppointment();
        $confirmed = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $appointment->lawyer_profile_id,
            'status' => AppointmentStatus::CONFIRMED->value,
            'appointment_date' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00:00',
        ]);

        $response = $this->actingAs($customer)->get(route('customer.appointments.index', ['filter' => 'confirmed']));

        $response->assertSee($confirmed->reference);
        $response->assertDontSee($appointment->reference);
    }
}
