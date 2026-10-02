<?php

namespace Tests\Feature\Lawyer;

use App\Models\LawyerAvailability;
use App\Models\LawyerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 Part 1: lawyer availability CRUD, ownership and validation
 * (requirements #7-#9, #31).
 */
class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function lawyerWithProfile(): User
    {
        $lawyer = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->for($lawyer, 'user')->create();

        return $lawyer;
    }

    public function test_lawyer_can_create_own_recurring_availability(): void
    {
        $lawyer = $this->lawyerWithProfile();

        $response = $this->actingAs($lawyer)->post(route('lawyer.availability.store'), [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_available' => '1',
        ]);

        $response->assertRedirect(route('lawyer.availability.index'));
        $this->assertDatabaseHas('lawyer_availabilities', [
            'lawyer_profile_id' => $lawyer->lawyerProfile->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_recurring' => 1,
            'is_available' => 1,
        ]);
    }

    public function test_lawyer_can_create_own_one_off_date_availability(): void
    {
        $lawyer = $this->lawyerWithProfile();
        $date = now()->addDays(5)->toDateString();

        $response = $this->actingAs($lawyer)->post(route('lawyer.availability.store'), [
            'specific_date' => $date,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $response->assertRedirect(route('lawyer.availability.index'));
        $this->assertDatabaseHas('lawyer_availabilities', [
            'lawyer_profile_id' => $lawyer->lawyerProfile->id,
            'specific_date' => $date,
            'is_recurring' => 0,
        ]);
    }

    public function test_availability_requires_exactly_one_of_day_of_week_or_specific_date(): void
    {
        $lawyer = $this->lawyerWithProfile();

        // Neither supplied.
        $this->actingAs($lawyer)->post(route('lawyer.availability.store'), [
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertSessionHasErrors('day_of_week');

        // Both supplied.
        $this->actingAs($lawyer)->post(route('lawyer.availability.store'), [
            'day_of_week' => 1,
            'specific_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertSessionHasErrors('day_of_week');

        $this->assertDatabaseCount('lawyer_availabilities', 0);
    }

    public function test_invalid_weekday_is_rejected(): void
    {
        $lawyer = $this->lawyerWithProfile();

        $this->actingAs($lawyer)->post(route('lawyer.availability.store'), [
            'day_of_week' => 7,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertSessionHasErrors('day_of_week');

        $this->assertDatabaseCount('lawyer_availabilities', 0);
    }

    public function test_start_time_must_be_before_end_time(): void
    {
        $lawyer = $this->lawyerWithProfile();

        $this->actingAs($lawyer)->post(route('lawyer.availability.store'), [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '08:00',
        ])->assertSessionHasErrors('end_time');

        $this->assertDatabaseCount('lawyer_availabilities', 0);
    }

    public function test_overlapping_recurring_availability_is_rejected(): void
    {
        $lawyer = $this->lawyerWithProfile();
        LawyerAvailability::factory()->create([
            'lawyer_profile_id' => $lawyer->lawyerProfile->id,
            'day_of_week' => 1,
            'specific_date' => null,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->actingAs($lawyer)->post(route('lawyer.availability.store'), [
            'day_of_week' => 1,
            'start_time' => '11:00',
            'end_time' => '14:00',
        ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('lawyer_availabilities', 1);
    }

    public function test_non_overlapping_availability_on_the_same_day_is_allowed(): void
    {
        $lawyer = $this->lawyerWithProfile();
        LawyerAvailability::factory()->create([
            'lawyer_profile_id' => $lawyer->lawyerProfile->id,
            'day_of_week' => 1,
            'specific_date' => null,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->actingAs($lawyer)->post(route('lawyer.availability.store'), [
            'day_of_week' => 1,
            'start_time' => '13:00',
            'end_time' => '15:00',
        ])->assertRedirect(route('lawyer.availability.index'));

        $this->assertDatabaseCount('lawyer_availabilities', 2);
    }

    public function test_lawyer_can_update_own_availability(): void
    {
        $lawyer = $this->lawyerWithProfile();
        $availability = LawyerAvailability::factory()->create([
            'lawyer_profile_id' => $lawyer->lawyerProfile->id,
            'day_of_week' => 2,
            'specific_date' => null,
        ]);

        $this->actingAs($lawyer)->put(route('lawyer.availability.update', $availability), [
            'day_of_week' => 2,
            'start_time' => '08:00',
            'end_time' => '11:00',
        ])->assertRedirect(route('lawyer.availability.index'));

        $this->assertDatabaseHas('lawyer_availabilities', [
            'id' => $availability->id,
            'start_time' => '08:00:00',
            'end_time' => '11:00:00',
        ]);
    }

    public function test_updating_availability_to_its_own_unchanged_time_does_not_reject_itself_as_overlapping(): void
    {
        $lawyer = $this->lawyerWithProfile();
        $availability = LawyerAvailability::factory()->create([
            'lawyer_profile_id' => $lawyer->lawyerProfile->id,
            'day_of_week' => 3,
            'specific_date' => null,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->actingAs($lawyer)->put(route('lawyer.availability.update', $availability), [
            'day_of_week' => 3,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_available' => '1',
        ])->assertSessionHasNoErrors();
    }

    public function test_lawyer_can_deactivate_and_reactivate_own_availability(): void
    {
        $lawyer = $this->lawyerWithProfile();
        $availability = LawyerAvailability::factory()->create([
            'lawyer_profile_id' => $lawyer->lawyerProfile->id,
            'is_available' => true,
        ]);

        $this->actingAs($lawyer)->patch(route('lawyer.availability.toggle', $availability))
            ->assertRedirect(route('lawyer.availability.index'));
        $this->assertFalse($availability->fresh()->is_available);

        $this->actingAs($lawyer)->patch(route('lawyer.availability.toggle', $availability))
            ->assertRedirect(route('lawyer.availability.index'));
        $this->assertTrue($availability->fresh()->is_available);
    }

    public function test_lawyer_can_delete_own_availability(): void
    {
        $lawyer = $this->lawyerWithProfile();
        $availability = LawyerAvailability::factory()->create([
            'lawyer_profile_id' => $lawyer->lawyerProfile->id,
        ]);

        $this->actingAs($lawyer)->delete(route('lawyer.availability.destroy', $availability))
            ->assertRedirect(route('lawyer.availability.index'));

        $this->assertDatabaseMissing('lawyer_availabilities', ['id' => $availability->id]);
    }

    public function test_lawyer_cannot_view_edit_update_toggle_or_delete_another_lawyers_availability(): void
    {
        $lawyerA = $this->lawyerWithProfile();
        $lawyerB = $this->lawyerWithProfile();

        $availabilityB = LawyerAvailability::factory()->create([
            'lawyer_profile_id' => $lawyerB->lawyerProfile->id,
        ]);

        $this->actingAs($lawyerA)->get(route('lawyer.availability.edit', $availabilityB))->assertForbidden();

        $this->actingAs($lawyerA)->put(route('lawyer.availability.update', $availabilityB), [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertForbidden();

        $this->actingAs($lawyerA)->patch(route('lawyer.availability.toggle', $availabilityB))->assertForbidden();

        $this->actingAs($lawyerA)->delete(route('lawyer.availability.destroy', $availabilityB))->assertForbidden();

        $this->assertDatabaseHas('lawyer_availabilities', ['id' => $availabilityB->id]);
    }

    public function test_customer_cannot_access_availability_management_routes(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('lawyer.availability.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('lawyer.availability.store'), [
            'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '10:00',
        ])->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_for_availability_management_routes(): void
    {
        $this->get(route('lawyer.availability.index'))->assertRedirect(route('login'));
    }
}
