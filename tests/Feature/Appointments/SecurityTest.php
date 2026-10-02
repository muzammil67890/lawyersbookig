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
 * Phase 6 Part 1: private-data access and IDOR protection (requirements
 * #24, #28, #29, #34).
 */
class SecurityTest extends TestCase
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

    public function test_customer_a_cannot_view_customer_bs_appointment(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        $appointment = Appointment::factory()->create([
            'customer_id' => $customerB->id,
            'lawyer_profile_id' => $lawyer->id,
            'service_id' => $service->id,
            'appointment_date' => $date->toDateString(),
        ]);

        $this->actingAs($customerA)
            ->get(route('customer.appointments.show', $appointment))
            ->assertForbidden();
    }

    public function test_customer_b_can_view_their_own_appointment(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customerB = User::factory()->create();

        $appointment = Appointment::factory()->create([
            'customer_id' => $customerB->id,
            'lawyer_profile_id' => $lawyer->id,
            'service_id' => $service->id,
            'appointment_date' => $date->toDateString(),
        ]);

        $this->actingAs($customerB)
            ->get(route('customer.appointments.show', $appointment))
            ->assertOk();
    }

    public function test_another_lawyer_cannot_view_a_different_lawyers_appointment(): void
    {
        [$lawyer, $service, $date] = $this->bookableLawyer();
        $customer = User::factory()->create();
        $otherLawyerUser = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->for($otherLawyerUser, 'user')->create();

        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
            'service_id' => $service->id,
            'appointment_date' => $date->toDateString(),
        ]);

        // customer.appointments.show only routes for the customer role;
        // a lawyer hitting the customer-scoped route is blocked by the
        // role middleware before the policy is even reached.
        $this->actingAs($otherLawyerUser)
            ->get(route('customer.appointments.show', $appointment))
            ->assertForbidden();
    }

    public function test_an_arbitrary_service_id_cannot_bypass_the_lawyer_service_relationship(): void
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

    public function test_an_ineligible_lawyer_id_cannot_be_reached_through_the_slots_endpoint(): void
    {
        $lawyer = LawyerProfile::factory()->pending()->create();
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->getJson(route('customer.appointments.slots', $lawyer->id).'?date='.now()->addDay()->toDateString())
            ->assertNotFound();
    }

    public function test_an_ineligible_lawyer_id_cannot_be_reached_through_the_booking_form_page(): void
    {
        $lawyer = LawyerProfile::factory()->rejected()->create();
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('customer.appointments.create', $lawyer->id))
            ->assertNotFound();
    }
}
