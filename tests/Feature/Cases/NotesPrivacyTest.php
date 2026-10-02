<?php

namespace Tests\Feature\Cases;

use App\Enums\CaseStatus;
use App\Models\LawyerProfile;
use App\Models\LegalCase;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 11 QA fix: `cases.notes` used to be shared between the customer
 * and the lawyer (each side's "private notes" were readable and
 * overwritable by the other). `notes` is now the lawyer's private
 * field and `customer_notes` is the customer's; a cancellation reason
 * gets its own `cancellation_reason` column instead of being appended
 * into whichever "notes" column happened to be shared. This file
 * exercises the corrected privacy boundary end to end; the lifecycle
 * mechanics themselves (transitions, IDOR on the case itself, etc.)
 * are already covered by LifecycleTest and are not repeated here.
 */
class NotesPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function pendingCase(): array
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $service = Service::factory()->create();
        $lawyer->services()->attach($service->id);
        $customer = User::factory()->create();

        $case = LegalCase::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
            'service_id' => $service->id,
            'status' => CaseStatus::PENDING->value,
        ]);

        return [$case, $customer, $lawyerUser];
    }

    public function test_customer_can_create_and_update_their_own_notes(): void
    {
        [$case, $customer] = $this->pendingCase();

        $this->actingAs($customer)->put(route('customer.cases.update', $case), [
            'customer_notes' => 'First draft of my notes.',
        ]);
        $this->assertSame('First draft of my notes.', $case->fresh()->customer_notes);

        $this->actingAs($customer)->put(route('customer.cases.update', $case), [
            'customer_notes' => 'Revised notes.',
        ]);
        $this->assertSame('Revised notes.', $case->fresh()->customer_notes);
    }

    public function test_lawyer_can_create_and_update_their_own_notes(): void
    {
        [$case, , $lawyerUser] = $this->pendingCase();

        $this->actingAs($lawyerUser)->put(route('lawyer.cases.update', $case), [
            'notes' => 'First draft of my private notes.',
        ]);
        $this->assertSame('First draft of my private notes.', $case->fresh()->notes);

        $this->actingAs($lawyerUser)->put(route('lawyer.cases.update', $case), [
            'notes' => 'Revised private notes.',
        ]);
        $this->assertSame('Revised private notes.', $case->fresh()->notes);
    }

    public function test_customer_cannot_read_the_lawyers_private_notes(): void
    {
        [$case, $customer, $lawyerUser] = $this->pendingCase();
        $case->update(['notes' => 'CONFIDENTIAL LAWYER STRATEGY']);

        $response = $this->actingAs($customer)->get(route('customer.cases.show', $case));

        $response->assertOk();
        $response->assertDontSee('CONFIDENTIAL LAWYER STRATEGY');
    }

    public function test_lawyer_cannot_read_the_customers_private_notes(): void
    {
        [$case, , $lawyerUser] = $this->pendingCase();
        $case->update(['customer_notes' => 'CONFIDENTIAL CUSTOMER DETAIL']);

        $response = $this->actingAs($lawyerUser)->get(route('lawyer.cases.show', $case));

        $response->assertOk();
        $response->assertDontSee('CONFIDENTIAL CUSTOMER DETAIL');
    }

    public function test_customer_cannot_overwrite_the_lawyers_notes_by_submitting_notes(): void
    {
        [$case, $customer] = $this->pendingCase();
        $case->update(['notes' => 'Original lawyer notes']);

        $this->actingAs($customer)->put(route('customer.cases.update', $case), [
            'notes' => 'Hijacked by customer',
            'customer_notes' => 'My own notes',
        ]);

        $case->refresh();
        $this->assertSame('Original lawyer notes', $case->notes);
        $this->assertSame('My own notes', $case->customer_notes);
    }

    public function test_lawyer_cannot_overwrite_the_customers_notes_by_submitting_customer_notes(): void
    {
        [$case, , $lawyerUser] = $this->pendingCase();
        $case->update(['customer_notes' => 'Original customer notes']);

        $this->actingAs($lawyerUser)->put(route('lawyer.cases.update', $case), [
            'customer_notes' => 'Hijacked by lawyer',
            'notes' => 'My own notes',
        ]);

        $case->refresh();
        $this->assertSame('Original customer notes', $case->customer_notes);
        $this->assertSame('My own notes', $case->notes);
    }

    public function test_admin_case_view_shows_both_notes_separately_labelled(): void
    {
        [$case] = $this->pendingCase();
        $case->update([
            'customer_notes' => 'CUSTOMER SIDE NOTE',
            'notes' => 'LAWYER SIDE NOTE',
        ]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.cases.show', $case));

        $response->assertOk();
        $response->assertSeeInOrder(['Customer Notes', 'CUSTOMER SIDE NOTE', 'Lawyer Notes', 'LAWYER SIDE NOTE']);
    }

    public function test_admin_without_access_cannot_view_case_notes_via_the_existing_access_rules(): void
    {
        [$case] = $this->pendingCase();
        $case->update(['customer_notes' => 'CUSTOMER SIDE NOTE', 'notes' => 'LAWYER SIDE NOTE']);
        $customer = User::factory()->create();
        $lawyerUser = User::factory()->lawyer()->create();

        // Non-admin, non-party users still get the ordinary access
        // failure -- the notes split doesn't change who may open the
        // case at all, only what each party sees inside it. Customer and
        // lawyer routes are role-gated before the policy even runs, so a
        // customer hitting a lawyer route (and vice versa) is a 403, not
        // a 404.
        $this->actingAs($customer)->get(route('lawyer.cases.show', $case))->assertForbidden();
        $this->actingAs($lawyerUser)->get(route('customer.cases.show', $case))->assertForbidden();
    }

    public function test_cancellation_reason_is_stored_separately_and_does_not_touch_either_partys_private_notes(): void
    {
        [$case, $customer] = $this->pendingCase();
        $case->update(['notes' => 'Pre-existing lawyer notes', 'customer_notes' => 'Pre-existing customer notes']);

        $this->actingAs($customer)->post(route('customer.cases.cancel', $case), [
            'reason' => 'No longer needed',
        ]);

        $case->refresh();
        $this->assertSame(CaseStatus::CANCELLED, $case->status);
        $this->assertSame('No longer needed', $case->cancellation_reason);
        // Cancelling must not silently graft the reason onto -- or wipe --
        // either party's own private notes.
        $this->assertSame('Pre-existing lawyer notes', $case->notes);
        $this->assertSame('Pre-existing customer notes', $case->customer_notes);
    }

    public function test_mass_assignment_of_the_opposite_partys_notes_field_is_ignored_not_erroring(): void
    {
        [$case, $customer, $lawyerUser] = $this->pendingCase();

        // A tampered payload naming both fields should not error out and
        // should not cross the privacy boundary -- the request field the
        // actor isn't allowed to touch is silently dropped, not rejected
        // with a validation error (it's a legitimate field, just not for
        // this role), and not applied.
        $this->actingAs($customer)
            ->put(route('customer.cases.update', $case), [
                'notes' => 'attempted takeover',
                'customer_notes' => 'legit customer note',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($case->fresh()->notes);
        $this->assertSame('legit customer note', $case->fresh()->customer_notes);

        $this->actingAs($lawyerUser)
            ->put(route('lawyer.cases.update', $case), [
                'customer_notes' => 'attempted takeover',
                'notes' => 'legit lawyer note',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('legit customer note', $case->fresh()->customer_notes);
        $this->assertSame('legit lawyer note', $case->fresh()->notes);
    }
}
