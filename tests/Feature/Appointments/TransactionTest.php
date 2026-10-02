<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\LawyerAvailability;
use App\Models\LawyerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 Part 1: transactional booking integrity (requirements #20,
 * #21, #35). Every failure path below must leave the appointments
 * table exactly as it was before the request -- never a partial or
 * invalid row.
 */
class TransactionTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_a_conflicting_booking_attempt_leaves_no_partial_row(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customer = User::factory()->create();

        Appointment::factory()->create([
            'lawyer_profile_id' => $lawyer->id,
            'service_id' => $service->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('appointments', 1);

        $this->actingAs($customer)->post(route('customer.appointments.store', $lawyer->id), [
            'service_id' => $service->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => '10:00',
            'consultation_type' => 'online',
        ])->assertSessionHasErrors('start_time');

        // Still exactly the one pre-existing appointment -- the failed
        // attempt created nothing.
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_a_booking_attempt_with_an_invalid_service_leaves_no_row(): void
    {
        [$lawyer, , $date] = $this->bookableLawyer();
        $unrelatedService = Service::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('customer.appointments.store', $lawyer->id), [
            'service_id' => $unrelatedService->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => '10:00',
            'consultation_type' => 'online',
        ])->assertSessionHasErrors('service_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_booking_attempt_outside_availability_leaves_no_row(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('customer.appointments.store', $lawyer->id), [
            'service_id' => $service->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => '20:00',
            'consultation_type' => 'online',
        ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_an_unauthorized_attempt_leaves_no_row(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $lawyerUser = User::factory()->lawyer()->create();

        $this->actingAs($lawyerUser)->post(route('customer.appointments.store', $lawyer->id), [
            'service_id' => $service->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => '10:00',
            'consultation_type' => 'online',
        ])->assertForbidden();

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_successful_booking_commits_exactly_one_row_with_all_expected_fields(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('customer.appointments.store', $lawyer->id), [
            'service_id' => $service->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => '10:00',
            'consultation_type' => 'online',
            'reason' => 'Contract review',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('appointments', 1);
        $appointment = Appointment::first();
        $this->assertNotEmpty($appointment->reference);
        $this->assertSame('10:00:00', $appointment->start_time);
        $this->assertSame('10:30:00', $appointment->end_time);
    }
}
