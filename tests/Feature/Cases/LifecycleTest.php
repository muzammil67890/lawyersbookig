<?php

namespace Tests\Feature\Cases;

use App\Enums\CaseStatus;
use App\Models\LawyerProfile;
use App\Models\LegalCase;
use App\Models\Service;
use App\Models\User;
use App\Notifications\CaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Phase 7: case creation, ownership/IDOR, lifecycle transitions
 * (activate/start-progress/complete/close/cancel), status-tampering
 * resistance, and notification payload safety. Mirrors
 * tests/Feature/Appointments/LifecycleTest.php.
 */
class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function approvedLawyer(): array
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $service = Service::factory()->create();
        $lawyer->services()->attach($service->id);

        return [$lawyerUser, $lawyer, $service];
    }

    protected function pendingCase(): array
    {
        [$lawyerUser, $lawyer, $service] = $this->approvedLawyer();
        $customer = User::factory()->create();

        $case = LegalCase::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
            'service_id' => $service->id,
            'status' => CaseStatus::PENDING->value,
        ]);

        return [$case, $customer, $lawyerUser, $lawyer, $service];
    }

    protected function activeCase(): array
    {
        [$case, $customer, $lawyerUser, $lawyer, $service] = $this->pendingCase();
        $case->update(['status' => CaseStatus::ACTIVE->value]);

        return [$case, $customer, $lawyerUser, $lawyer, $service];
    }

    protected function inProgressCase(): array
    {
        [$case, $customer, $lawyerUser, $lawyer, $service] = $this->activeCase();
        $case->update(['status' => CaseStatus::IN_PROGRESS->value]);

        return [$case, $customer, $lawyerUser, $lawyer, $service];
    }

    protected function completedCase(): array
    {
        [$case, $customer, $lawyerUser, $lawyer, $service] = $this->inProgressCase();
        $case->update(['status' => CaseStatus::COMPLETED->value]);

        return [$case, $customer, $lawyerUser, $lawyer, $service];
    }

    // -- Creation -----------------------------------------------------

    public function test_customer_can_open_a_case_with_an_eligible_lawyer(): void
    {
        Notification::fake();
        [$lawyerUser, $lawyer, $service] = $this->approvedLawyer();
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->post(route('customer.cases.store', $lawyer->id), [
            'title' => 'Property dispute',
            'description' => 'Boundary dispute with a neighbour.',
            'service_id' => $service->id,
        ]);

        $case = LegalCase::first();
        $this->assertNotNull($case);
        $this->assertSame($customer->id, $case->customer_id);
        $this->assertSame($lawyer->id, $case->lawyer_profile_id);
        $this->assertSame(CaseStatus::PENDING, $case->status);
        $response->assertRedirect(route('customer.cases.show', $case));
        Notification::assertSentTo($lawyerUser, CaseNotification::class);
    }

    public function test_case_creation_ignores_a_smuggled_customer_id(): void
    {
        [$lawyerUser, $lawyer, $service] = $this->approvedLawyer();
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();

        $this->actingAs($customer)->post(route('customer.cases.store', $lawyer->id), [
            'title' => 'Case title',
            'description' => 'Description text.',
            'customer_id' => $otherCustomer->id,
        ]);

        $case = LegalCase::first();
        $this->assertSame($customer->id, $case->customer_id);
    }

    public function test_case_creation_ignores_a_smuggled_status_and_case_number(): void
    {
        [$lawyerUser, $lawyer] = $this->approvedLawyer();
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('customer.cases.store', $lawyer->id), [
            'title' => 'Case title',
            'description' => 'Description text.',
            'status' => 'closed',
            'case_number' => 'HACKED-1',
        ]);

        $case = LegalCase::first();
        $this->assertSame(CaseStatus::PENDING, $case->status);
        $this->assertNotSame('HACKED-1', $case->case_number);
    }

    public function test_a_lawyer_cannot_open_a_case(): void
    {
        [, $lawyer] = $this->approvedLawyer();
        $someLawyerUser = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->create(['user_id' => $someLawyerUser->id]);

        $this->actingAs($someLawyerUser)
            ->post(route('customer.cases.store', $lawyer->id), ['title' => 'x', 'description' => 'y'])
            ->assertForbidden(); // role:customer middleware blocks a lawyer from /customer/* entirely
    }

    public function test_a_case_cannot_be_opened_with_a_pending_unapproved_lawyer(): void
    {
        $pendingLawyerUser = User::factory()->lawyer()->create();
        $pendingLawyer = LawyerProfile::factory()->pending()->create(['user_id' => $pendingLawyerUser->id]);
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('customer.cases.store', $pendingLawyer->id), ['title' => 'x', 'description' => 'y'])
            ->assertSessionHasErrors('lawyer');

        $this->assertDatabaseCount('cases', 0);
    }

    // -- Viewing / IDOR -------------------------------------------------

    public function test_customer_can_view_their_own_case(): void
    {
        [$case, $customer] = $this->pendingCase();

        $this->actingAs($customer)
            ->get(route('customer.cases.show', $case))
            ->assertOk()
            ->assertSee($case->case_number);
    }

    public function test_customer_cannot_view_another_customers_case(): void
    {
        [$case] = $this->pendingCase();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->get(route('customer.cases.show', $case))
            ->assertForbidden();
    }

    public function test_lawyer_can_view_their_assigned_case(): void
    {
        [$case, , $lawyerUser] = $this->pendingCase();

        $this->actingAs($lawyerUser)
            ->get(route('lawyer.cases.show', $case))
            ->assertOk()
            ->assertSee($case->case_number);
    }

    public function test_lawyer_cannot_view_an_unrelated_case(): void
    {
        [$case] = $this->pendingCase();
        $otherLawyerUser = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->create(['user_id' => $otherLawyerUser->id]);

        $this->actingAs($otherLawyerUser)
            ->get(route('lawyer.cases.show', $case))
            ->assertForbidden();
    }

    public function test_lawyer_cannot_view_another_lawyers_case_through_the_lawyer_route(): void
    {
        [$case] = $this->pendingCase();
        [, $otherLawyer] = $this->approvedLawyer();

        $this->actingAs($otherLawyer->user)
            ->get(route('lawyer.cases.show', $case))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$case] = $this->pendingCase();

        $this->get(route('customer.cases.show', $case))->assertRedirect(route('login'));
    }

    public function test_customer_cannot_use_the_lawyer_route_for_their_own_case(): void
    {
        [$case, $customer] = $this->pendingCase();

        $this->actingAs($customer)
            ->get(route('lawyer.cases.show', $case))
            ->assertForbidden(); // role:lawyer middleware never lets a customer into /lawyer/* at all
    }

    public function test_admin_can_view_any_case(): void
    {
        [$case] = $this->pendingCase();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.cases.show', $case))
            ->assertOk()
            ->assertSee($case->case_number);
    }

    public function test_manipulated_route_id_returns_not_found_not_a_leak(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('customer.cases.show', 999999))
            ->assertNotFound();
    }

    // -- Update -----------------------------------------------------------

    public function test_customer_can_update_title_description_and_their_own_notes(): void
    {
        [$case, $customer] = $this->pendingCase();

        $this->actingAs($customer)->put(route('customer.cases.update', $case), [
            'title' => 'Updated title',
            'description' => 'Updated description',
            'customer_notes' => 'My private notes',
        ]);

        $case->refresh();
        $this->assertSame('Updated title', $case->title);
        $this->assertSame('Updated description', $case->description);
        $this->assertSame('My private notes', $case->customer_notes);
    }

    public function test_lawyer_update_only_touches_notes_never_title_or_description(): void
    {
        [$case, , $lawyerUser] = $this->pendingCase();
        $originalTitle = $case->title;
        $originalDescription = $case->description;

        $this->actingAs($lawyerUser)->put(route('lawyer.cases.update', $case), [
            'title' => 'Malicious title change',
            'description' => 'Malicious description change',
            'notes' => 'Lawyer notes',
        ]);

        $case->refresh();
        $this->assertSame($originalTitle, $case->title);
        $this->assertSame($originalDescription, $case->description);
        $this->assertSame('Lawyer notes', $case->notes);
    }

    public function test_customer_cannot_update_another_customers_case(): void
    {
        [$case] = $this->pendingCase();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->put(route('customer.cases.update', $case), ['title' => 'x'])
            ->assertForbidden();
    }

    public function test_a_closed_case_can_no_longer_be_updated(): void
    {
        [$case, $customer, , , ] = $this->completedCase();
        $case->update(['status' => CaseStatus::CLOSED->value]);

        $this->actingAs($customer)
            ->put(route('customer.cases.update', $case), ['title' => 'New title'])
            ->assertForbidden();
    }

    // -- Lifecycle: activate ------------------------------------------------

    public function test_lawyer_can_activate_a_pending_case(): void
    {
        Notification::fake();
        [$case, $customer, $lawyerUser] = $this->pendingCase();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.cases.activate', $case))
            ->assertRedirect(route('lawyer.cases.show', $case));

        $this->assertSame(CaseStatus::ACTIVE, $case->fresh()->status);
        Notification::assertSentTo($customer, CaseNotification::class);
    }

    public function test_customer_cannot_activate_their_own_case(): void
    {
        [$case, $customer] = $this->pendingCase();

        $this->actingAs($customer)
            ->post(route('lawyer.cases.activate', $case))
            ->assertForbidden(); // role:lawyer middleware blocks the customer from /lawyer/* entirely

        $this->assertSame(CaseStatus::PENDING, $case->fresh()->status);
    }

    public function test_an_unrelated_lawyer_cannot_activate_a_case(): void
    {
        [$case] = $this->pendingCase();
        [$otherLawyerUser] = $this->approvedLawyer();

        $this->actingAs($otherLawyerUser)
            ->post(route('lawyer.cases.activate', $case))
            ->assertForbidden();

        $this->assertSame(CaseStatus::PENDING, $case->fresh()->status);
    }

    public function test_an_already_active_case_cannot_be_activated_again(): void
    {
        [$case, , $lawyerUser] = $this->activeCase();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.cases.activate', $case))
            ->assertSessionHasErrors('case');

        $this->assertSame(CaseStatus::ACTIVE, $case->fresh()->status);
    }

    // -- Lifecycle: start progress / complete / close -----------------------

    public function test_lawyer_can_move_an_active_case_to_in_progress(): void
    {
        [$case, , $lawyerUser] = $this->activeCase();

        $this->actingAs($lawyerUser)->post(route('lawyer.cases.start-progress', $case));

        $this->assertSame(CaseStatus::IN_PROGRESS, $case->fresh()->status);
    }

    public function test_a_pending_case_cannot_skip_straight_to_in_progress(): void
    {
        [$case, , $lawyerUser] = $this->pendingCase();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.cases.start-progress', $case))
            ->assertSessionHasErrors('case');

        $this->assertSame(CaseStatus::PENDING, $case->fresh()->status);
    }

    public function test_lawyer_can_complete_an_in_progress_case(): void
    {
        [$case, , $lawyerUser] = $this->inProgressCase();

        $this->actingAs($lawyerUser)->post(route('lawyer.cases.complete', $case));

        $this->assertSame(CaseStatus::COMPLETED, $case->fresh()->status);
    }

    public function test_lawyer_can_close_a_completed_case(): void
    {
        [$case, , $lawyerUser] = $this->completedCase();

        $this->actingAs($lawyerUser)->post(route('lawyer.cases.close', $case));

        $this->assertSame(CaseStatus::CLOSED, $case->fresh()->status);
    }

    public function test_an_active_case_cannot_be_closed_directly(): void
    {
        [$case, , $lawyerUser] = $this->activeCase();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.cases.close', $case))
            ->assertSessionHasErrors('case');

        $this->assertSame(CaseStatus::ACTIVE, $case->fresh()->status);
    }

    // -- Cancel -------------------------------------------------------------

    public function test_customer_can_cancel_a_pending_case(): void
    {
        Notification::fake();
        [$case, $customer, $lawyerUser] = $this->pendingCase();

        $this->actingAs($customer)
            ->post(route('customer.cases.cancel', $case), ['reason' => 'Changed my mind'])
            ->assertRedirect(route('customer.cases.show', $case));

        $case->refresh();
        $this->assertSame(CaseStatus::CANCELLED, $case->status);
        $this->assertSame('Changed my mind', $case->cancellation_reason);
        $this->assertNull($case->notes);
        Notification::assertSentTo($lawyerUser, CaseNotification::class);
    }

    public function test_customer_cannot_cancel_an_active_case(): void
    {
        [$case, $customer] = $this->activeCase();

        $this->actingAs($customer)
            ->post(route('customer.cases.cancel', $case))
            ->assertSessionHasErrors('case');

        $this->assertSame(CaseStatus::ACTIVE, $case->fresh()->status);
    }

    public function test_lawyer_can_cancel_an_active_case(): void
    {
        [$case, , $lawyerUser] = $this->activeCase();

        $this->actingAs($lawyerUser)->post(route('lawyer.cases.cancel', $case));

        $this->assertSame(CaseStatus::CANCELLED, $case->fresh()->status);
    }

    public function test_lawyer_cannot_cancel_an_in_progress_case(): void
    {
        [$case, , $lawyerUser] = $this->inProgressCase();

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.cases.cancel', $case))
            ->assertSessionHasErrors('case');

        $this->assertSame(CaseStatus::IN_PROGRESS, $case->fresh()->status);
    }

    public function test_admin_can_cancel_an_in_progress_case_for_oversight(): void
    {
        [$case] = $this->inProgressCase();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.cases.cancel', $case));

        $this->assertSame(CaseStatus::CANCELLED, $case->fresh()->status);
    }

    public function test_customer_cannot_cancel_another_customers_case(): void
    {
        [$case] = $this->pendingCase();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->post(route('customer.cases.cancel', $case))
            ->assertForbidden();

        $this->assertSame(CaseStatus::PENDING, $case->fresh()->status);
    }

    public function test_a_closed_case_cannot_be_cancelled(): void
    {
        [$case, , $lawyerUser] = $this->completedCase();
        $case->update(['status' => CaseStatus::CLOSED->value]);

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.cases.cancel', $case))
            ->assertSessionHasErrors('case');
    }

    // -- Status tampering -----------------------------------------------------

    public function test_status_cannot_be_set_directly_through_the_cancel_request(): void
    {
        [$case, $customer] = $this->pendingCase();

        $this->actingAs($customer)->post(route('customer.cases.cancel', $case), [
            'reason' => 'ignored',
            'status' => 'active',
        ]);

        $this->assertSame(CaseStatus::CANCELLED, $case->fresh()->status);
    }

    public function test_update_request_cannot_smuggle_a_status_or_ownership_change(): void
    {
        [$case, $customer] = $this->pendingCase();
        $otherCustomer = User::factory()->create();

        $this->actingAs($customer)->put(route('customer.cases.update', $case), [
            'title' => 'Fine',
            'status' => 'closed',
            'customer_id' => $otherCustomer->id,
            'lawyer_profile_id' => 999,
        ]);

        $case->refresh();
        $this->assertSame(CaseStatus::PENDING, $case->status);
        $this->assertSame($customer->id, $case->customer_id);
    }

    // -- Admin reassignment ---------------------------------------------------

    public function test_admin_can_reassign_a_case_to_a_different_lawyer(): void
    {
        Notification::fake();
        [$case, , $originalLawyerUser] = $this->pendingCase();
        [$newLawyerUser, $newLawyer] = $this->approvedLawyer();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.cases.assign-lawyer', $case), ['lawyer_profile_id' => $newLawyer->id])
            ->assertRedirect(route('admin.cases.show', $case));

        $this->assertSame($newLawyer->id, $case->fresh()->lawyer_profile_id);
        Notification::assertSentTo($newLawyerUser, CaseNotification::class);
        Notification::assertSentTo($originalLawyerUser, CaseNotification::class);
    }

    public function test_a_customer_cannot_reassign_their_own_case(): void
    {
        [$case, $customer] = $this->pendingCase();
        [, $newLawyer] = $this->approvedLawyer();

        $this->actingAs($customer)
            ->post(route('admin.cases.assign-lawyer', $case), ['lawyer_profile_id' => $newLawyer->id])
            ->assertForbidden(); // role:admin middleware blocks the customer from /admin/* entirely

        $this->assertNotSame($newLawyer->id, $case->fresh()->lawyer_profile_id);
    }

    public function test_a_lawyer_cannot_reassign_a_case_to_themselves(): void
    {
        [$case] = $this->pendingCase();
        [$otherLawyerUser, $otherLawyer] = $this->approvedLawyer();

        $this->actingAs($otherLawyerUser)
            ->post(route('admin.cases.assign-lawyer', $case), ['lawyer_profile_id' => $otherLawyer->id])
            ->assertForbidden();

        $this->assertNotSame($otherLawyer->id, $case->fresh()->lawyer_profile_id);
    }

    public function test_a_closed_case_cannot_be_reassigned(): void
    {
        [$case, , , , ] = $this->completedCase();
        $case->update(['status' => CaseStatus::CLOSED->value]);
        [, $newLawyer] = $this->approvedLawyer();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.cases.assign-lawyer', $case), ['lawyer_profile_id' => $newLawyer->id])
            ->assertSessionHasErrors('case');
    }

    public function test_reassignment_rejects_an_unapproved_lawyer(): void
    {
        [$case] = $this->pendingCase();
        $pendingLawyerUser = User::factory()->lawyer()->create();
        $pendingLawyer = LawyerProfile::factory()->pending()->create(['user_id' => $pendingLawyerUser->id]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.cases.assign-lawyer', $case), ['lawyer_profile_id' => $pendingLawyer->id])
            ->assertSessionHasErrors('lawyer_profile_id');
    }

    // -- Admin deletion ---------------------------------------------------

    public function test_admin_can_delete_a_cancelled_document_free_case(): void
    {
        [$case, $customer, $lawyerUser] = $this->pendingCase();
        $this->actingAs($customer)->post(route('customer.cases.cancel', $case));
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.cases.destroy', $case))
            ->assertRedirect(route('admin.cases.index'));

        $this->assertDatabaseMissing('cases', ['id' => $case->id]);
    }

    public function test_a_pending_case_cannot_be_deleted(): void
    {
        [$case] = $this->pendingCase();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete(route('admin.cases.destroy', $case));

        $this->assertDatabaseHas('cases', ['id' => $case->id]);
    }

    public function test_a_customer_cannot_delete_a_case(): void
    {
        [$case, $customer] = $this->pendingCase();
        $this->actingAs($customer)->post(route('customer.cases.cancel', $case));

        $this->actingAs($customer)
            ->delete(route('admin.cases.destroy', $case))
            ->assertForbidden();

        $this->assertDatabaseHas('cases', ['id' => $case->id]);
    }

    // -- Notification payload -------------------------------------------------

    public function test_notification_payload_carries_no_sensitive_data(): void
    {
        [$case, $customer, $lawyerUser] = $this->pendingCase();
        $case->update(['description' => 'Confidential: divorce proceedings against a public figure']);

        $this->actingAs($lawyerUser)->post(route('lawyer.cases.activate', $case));

        $notification = $customer->notifications()->first();
        $this->assertNotNull($notification);

        $payload = json_encode($notification->data);
        $this->assertStringNotContainsString('Confidential', $payload);
        $this->assertStringNotContainsString($customer->email, $payload);
        $this->assertArrayHasKey('event', $notification->data);
        $this->assertArrayHasKey('case_number', $notification->data);
    }

    // -- Listing / filters ------------------------------------------------

    public function test_customer_case_list_only_shows_their_own(): void
    {
        [$case, $customer] = $this->pendingCase();
        [$otherCase] = $this->pendingCase();

        $response = $this->actingAs($customer)->get(route('customer.cases.index'));

        $response->assertOk()->assertSee($case->case_number);
        $response->assertDontSee($otherCase->case_number);
    }

    public function test_lawyer_case_list_only_shows_their_own(): void
    {
        [$case, , $lawyerUser] = $this->pendingCase();
        [$otherCase] = $this->pendingCase();

        $response = $this->actingAs($lawyerUser)->get(route('lawyer.cases.index'));

        $response->assertOk()->assertSee($case->case_number);
        $response->assertDontSee($otherCase->case_number);
    }

    public function test_admin_case_list_shows_every_case(): void
    {
        [$case] = $this->pendingCase();
        [$otherCase] = $this->pendingCase();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.cases.index'));

        $response->assertOk()->assertSee($case->case_number)->assertSee($otherCase->case_number);
    }

    public function test_status_filter_only_returns_matching_cases(): void
    {
        [$case, $customer] = $this->pendingCase();
        [$activeCase] = $this->activeCase();
        LegalCase::where('id', $activeCase->id)->update(['customer_id' => $customer->id]);

        $response = $this->actingAs($customer)->get(route('customer.cases.index', ['status' => 'active']));

        $response->assertSee($activeCase->case_number);
        $response->assertDontSee($case->case_number);
    }

    public function test_a_suspended_customer_cannot_view_their_case(): void
    {
        [$case, $customer] = $this->pendingCase();
        $customer->update(['status' => 'suspended']);

        $this->actingAs($customer)
            ->get(route('customer.cases.show', $case))
            ->assertRedirect();
    }
}
