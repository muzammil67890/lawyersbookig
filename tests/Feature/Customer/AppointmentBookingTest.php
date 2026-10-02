<?php

namespace Tests\Feature\Customer;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\LawyerAvailability;
use App\Models\LawyerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 Part 1: customer appointment booking foundation (requirements
 * #12-#28, #32).
 */
class AppointmentBookingTest extends TestCase
{
    use RefreshDatabase;

    /** Builds an eligible, bookable lawyer with one offered service and a same-day availability window. */
    protected function bookableLawyer(): array
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        $service = Service::factory()->create();
        $lawyer->services()->attach($service->id);

        $date = now()->addDays(3)->startOfDay();

        LawyerAvailability::factory()->create([
            'lawyer_profile_id' => $lawyer->id,
            'day_of_week' => null,
            'specific_date' => $date->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_recurring' => false,
            'is_available' => true,
        ]);

        return [$lawyer, $service, $date];
    }

    protected function customer(): User
    {
        return User::factory()->create();
    }

    protected function validPayload(Service $service, $date, string $time = '10:00'): array
    {
        return [
            'service_id' => $service->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => $time,
            'consultation_type' => 'online',
            'reason' => 'Need advice on a contract dispute.',
        ];
    }

    public function test_customer_can_create_a_valid_appointment(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customer = $this->customer();

        $response = $this->actingAs($customer)->post(
            route('customer.appointments.store', $lawyer->id),
            $this->validPayload($service, $date)
        );

        $appointment = Appointment::first();
        $response->assertRedirect(route('customer.appointments.show', $appointment));

        $this->assertNotNull($appointment);
        $this->assertSame($customer->id, $appointment->customer_id);
        $this->assertSame($lawyer->id, $appointment->lawyer_profile_id);
        $this->assertSame($service->id, $appointment->service_id);
        $this->assertSame(AppointmentStatus::PENDING, $appointment->status);
        $this->assertSame('10:00:00', $appointment->start_time);
    }

    public function test_guest_cannot_create_an_appointment(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();

        $this->post(route('customer.appointments.store', $lawyer->id), $this->validPayload($service, $date))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_lawyer_cannot_use_the_customer_booking_endpoint(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $otherLawyerUser = User::factory()->lawyer()->create();

        $this->actingAs($otherLawyerUser)
            ->post(route('customer.appointments.store', $lawyer->id), $this->validPayload($service, $date))
            ->assertForbidden();

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_booking_a_nonexistent_lawyer_id_is_rejected(): void
    {
        $service = Service::factory()->create();
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post(route('customer.appointments.store', 999999), $this->validPayload($service, now()->addDays(2)))
            ->assertSessionHasErrors('lawyer');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_non_lawyer_user_id_cannot_be_booked_as_a_lawyer(): void
    {
        $plainCustomer = User::factory()->create();
        $service = Service::factory()->create();
        $customer = $this->customer();

        // There is no LawyerProfile row at all for a plain customer,
        // so its user id can never resolve through LawyerProfile::searchable().
        $this->actingAs($customer)
            ->post(route('customer.appointments.store', $plainCustomer->id), $this->validPayload($service, now()->addDays(2)))
            ->assertSessionHasErrors('lawyer');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_pending_lawyer_cannot_be_booked(): void
    {
        $lawyer = LawyerProfile::factory()->pending()->create();
        $service = Service::factory()->create();
        $lawyer->services()->attach($service->id);
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post(route('customer.appointments.store', $lawyer->id), $this->validPayload($service, now()->addDays(2)))
            ->assertSessionHasErrors('lawyer');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_rejected_lawyer_cannot_be_booked(): void
    {
        $lawyer = LawyerProfile::factory()->rejected()->create();
        $service = Service::factory()->create();
        $lawyer->services()->attach($service->id);
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post(route('customer.appointments.store', $lawyer->id), $this->validPayload($service, now()->addDays(2)))
            ->assertSessionHasErrors('lawyer');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_an_inactive_lawyer_cannot_be_booked(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create(['is_active' => false]);
        $service = Service::factory()->create();
        $lawyer->services()->attach($service->id);
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post(route('customer.appointments.store', $lawyer->id), $this->validPayload($service, now()->addDays(2)))
            ->assertSessionHasErrors('lawyer');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_lawyer_with_a_suspended_user_account_cannot_be_booked(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => User::factory()->lawyer()->suspended()]);
        $service = Service::factory()->create();
        $lawyer->services()->attach($service->id);
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post(route('customer.appointments.store', $lawyer->id), $this->validPayload($service, now()->addDays(2)))
            ->assertSessionHasErrors('lawyer');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_an_unrelated_service_not_offered_by_the_lawyer_is_rejected(): void
    {
        [$lawyer, , $date] = $this->bookableLawyer();
        $unrelatedService = Service::factory()->create();
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post(route('customer.appointments.store', $lawyer->id), $this->validPayload($unrelatedService, $date))
            ->assertSessionHasErrors('service_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_an_invalid_date_format_is_rejected(): void
    {
        [$lawyer, $service] = $this->bookableLawyer();
        $customer = $this->customer();

        $payload = $this->validPayload($service, now()->addDays(3));
        $payload['appointment_date'] = 'not-a-date';

        $this->actingAs($customer)
            ->post(route('customer.appointments.store', $lawyer->id), $payload)
            ->assertSessionHasErrors('appointment_date');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_past_date_is_rejected(): void
    {
        [$lawyer, $service] = $this->bookableLawyer();
        $customer = $this->customer();

        $payload = $this->validPayload($service, now()->subDays(2));

        $this->actingAs($customer)
            ->post(route('customer.appointments.store', $lawyer->id), $payload)
            ->assertSessionHasErrors('appointment_date');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_an_invalid_time_format_is_rejected(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customer = $this->customer();

        $payload = $this->validPayload($service, $date);
        $payload['start_time'] = '25:99';

        $this->actingAs($customer)
            ->post(route('customer.appointments.store', $lawyer->id), $payload)
            ->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_time_outside_availability_is_rejected(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customer = $this->customer();

        // Availability window is 09:00-17:00; 20:00 is outside it.
        $payload = $this->validPayload($service, $date, '20:00');

        $this->actingAs($customer)
            ->post(route('customer.appointments.store', $lawyer->id), $payload)
            ->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_the_authenticated_customer_is_assigned_regardless_of_any_spoofed_customer_id(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customer = $this->customer();
        $otherUser = User::factory()->create();

        $payload = $this->validPayload($service, $date);
        $payload['customer_id'] = $otherUser->id;

        $this->actingAs($customer)->post(route('customer.appointments.store', $lawyer->id), $payload);

        $appointment = Appointment::first();
        $this->assertSame($customer->id, $appointment->customer_id);
        $this->assertNotSame($otherUser->id, $appointment->customer_id);
    }

    public function test_a_suspended_customer_account_is_blocked_from_booking_even_mid_session(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customer = User::factory()->create(); // active at login time

        // Simulate an admin suspending the account mid-session: the
        // 'active' middleware re-checks status on every request, not
        // just at login.
        $customer->update(['status' => 'suspended']);

        $this->actingAs($customer)
            ->post(route('customer.appointments.store', $lawyer->id), $this->validPayload($service, $date))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_an_unverified_customer_cannot_book(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customer = User::factory()->unverified()->create();

        $response = $this->actingAs($customer)
            ->post(route('customer.appointments.store', $lawyer->id), $this->validPayload($service, $date));

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_the_correct_lawyer_and_service_are_assigned_on_a_successful_booking(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $otherLawyer = LawyerProfile::factory()->approved()->create();
        $customer = $this->customer();

        $payload = $this->validPayload($service, $date);
        $payload['lawyer_profile_id'] = $otherLawyer->id; // not a real field; must be ignored

        $this->actingAs($customer)->post(route('customer.appointments.store', $lawyer->id), $payload);

        $appointment = Appointment::first();
        $this->assertSame($lawyer->id, $appointment->lawyer_profile_id);
        $this->assertNotSame($otherLawyer->id, $appointment->lawyer_profile_id);
        $this->assertSame($service->id, $appointment->service_id);
    }
}
