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
 * Phase 6 Part 1: overlap/conflict detection (requirements #19, #33).
 *
 * Back-to-back appointments (one ends exactly when another begins) are
 * documented as ALLOWED: AppointmentAvailabilityService::hasConflict()
 * uses a half-open interval test (`start < existing.end AND end >
 * existing.start`), so an existing 09:00-09:30 appointment does not
 * block a new 09:30-10:00 request for the same lawyer.
 */
class ConflictTest extends TestCase
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

    protected function existingAppointment(LawyerProfile $lawyer, Service $service, $date, string $start, string $end): Appointment
    {
        return Appointment::factory()->create([
            'lawyer_profile_id' => $lawyer->id,
            'service_id' => $service->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'pending',
        ]);
    }

    protected function attemptBooking(LawyerProfile $lawyer, Service $service, $date, string $start): \Illuminate\Testing\TestResponse
    {
        $customer = User::factory()->create();

        return $this->actingAs($customer)->post(route('customer.appointments.store', $lawyer->id), [
            'service_id' => $service->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => $start,
            'consultation_type' => 'online',
        ]);
    }

    public function test_an_exact_duplicate_slot_is_rejected(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $this->existingAppointment($lawyer, $service, $date, '10:00:00', '10:30:00');

        $this->attemptBooking($lawyer, $service, $date, '10:00')->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_a_partial_overlap_is_rejected(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $this->existingAppointment($lawyer, $service, $date, '10:00:00', '10:30:00');

        // New 10:15-10:45 partially overlaps existing 10:00-10:30.
        $this->attemptBooking($lawyer, $service, $date, '10:15')->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_a_new_appointment_containing_an_existing_one_is_rejected(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        // Existing 10:35-10:45 sits entirely inside the new 30-minute
        // request (10:15-10:45), i.e. the new appointment contains it.
        $this->existingAppointment($lawyer, $service, $date, '10:35:00', '10:45:00');

        $this->attemptBooking($lawyer, $service, $date, '10:15')->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_an_existing_appointment_containing_the_new_one_is_rejected(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $this->existingAppointment($lawyer, $service, $date, '10:00:00', '12:00:00');

        $this->attemptBooking($lawyer, $service, $date, '10:30')->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_a_different_lawyer_may_be_booked_at_the_same_time(): void
    {
        [$lawyerA, $serviceA, $date] = $this->bookableLawyer();
        [$lawyerB, $serviceB] = $this->bookableLawyer();
        $this->existingAppointment($lawyerA, $serviceA, $date, '10:00:00', '10:30:00');

        $this->attemptBooking($lawyerB, $serviceB, $date, '10:00')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_a_non_overlapping_slot_for_the_same_lawyer_is_allowed(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $this->existingAppointment($lawyer, $service, $date, '10:00:00', '10:30:00');

        $this->attemptBooking($lawyer, $service, $date, '14:00')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_back_to_back_appointments_for_the_same_lawyer_are_allowed(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $this->existingAppointment($lawyer, $service, $date, '09:00:00', '09:30:00');

        // New request starts exactly when the existing one ends.
        $this->attemptBooking($lawyer, $service, $date, '09:30')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('appointments', 2);
    }
}
