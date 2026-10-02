<?php

namespace Tests\Feature\Admin;

use App\Enums\VerificationStatus;
use App\Models\LawyerProfile;
use App\Models\LawyerVerification;
use App\Models\User;
use App\Notifications\LawyerApplicationApproved;
use App\Notifications\LawyerApplicationRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LawyerVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function pendingLawyer(): User
    {
        $lawyer = User::factory()->lawyer()->create(['email_verified_at' => now()]);

        LawyerProfile::factory()->for($lawyer, 'user')->pending()->create();
        LawyerVerification::factory()->for($lawyer, 'user')->create(['status' => 'pending']);

        return $lawyer;
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create(['email_verified_at' => now()]);
    }

    public function test_the_verification_queue_index_requires_admin(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $lawyer = User::factory()->lawyer()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->get(route('admin.lawyer-verifications.index'))->assertForbidden();
        $this->actingAs($lawyer)->get(route('admin.lawyer-verifications.index'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.lawyer-verifications.index'))->assertRedirect(route('login'));
    }

    public function test_an_admin_can_view_the_pending_queue(): void
    {
        $admin = $this->admin();
        $lawyer = $this->pendingLawyer();

        $response = $this->actingAs($admin)->get(route('admin.lawyer-verifications.index'));

        $response->assertOk();
        $response->assertSee($lawyer->name);
    }

    public function test_an_admin_can_view_a_single_application(): void
    {
        $admin = $this->admin();
        $lawyer = $this->pendingLawyer();

        $this->actingAs($admin)
            ->get(route('admin.lawyer-verifications.show', $lawyer->lawyerVerification))
            ->assertOk();
    }

    public function test_an_admin_can_approve_a_pending_lawyer(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $lawyer = $this->pendingLawyer();
        $verification = $lawyer->lawyerVerification;

        $response = $this->actingAs($admin)->post(route('admin.lawyer-verifications.approve', $verification));

        $response->assertRedirect(route('admin.lawyer-verifications.show', $verification));

        $verification->refresh();
        $lawyer->refresh();

        $this->assertSame(VerificationStatus::APPROVED, $verification->status);
        $this->assertSame($admin->id, $verification->reviewed_by);
        $this->assertNotNull($verification->reviewed_at);
        $this->assertSame(VerificationStatus::APPROVED, $lawyer->lawyerProfile->verification_status);

        Notification::assertSentTo($lawyer, LawyerApplicationApproved::class);
    }

    public function test_approval_makes_the_lawyer_profile_eligible(): void
    {
        $admin = $this->admin();
        $lawyer = $this->pendingLawyer();
        // The factory default for is_public/is_active is true; approval
        // is the missing piece for eligibility.
        $lawyer->lawyerProfile->update(['is_public' => true, 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.lawyer-verifications.approve', $lawyer->lawyerVerification));

        $this->assertTrue($lawyer->lawyerProfile->fresh()->isEligible());
        $this->assertTrue(
            LawyerProfile::searchable()->whereKey($lawyer->lawyerProfile->id)->exists()
        );
    }

    public function test_an_admin_can_reject_a_pending_lawyer_with_a_reason(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $lawyer = $this->pendingLawyer();
        $verification = $lawyer->lawyerVerification;

        $response = $this->actingAs($admin)->post(route('admin.lawyer-verifications.reject', $verification), [
            'rejection_reason' => 'The CNIC image provided was blurry and unreadable.',
        ]);

        $response->assertRedirect(route('admin.lawyer-verifications.show', $verification));

        $verification->refresh();
        $lawyer->refresh();

        $this->assertSame(VerificationStatus::REJECTED, $verification->status);
        $this->assertSame('The CNIC image provided was blurry and unreadable.', $verification->rejection_reason);
        $this->assertSame(VerificationStatus::REJECTED, $lawyer->lawyerProfile->verification_status);
        $this->assertFalse($lawyer->lawyerProfile->isEligible());

        Notification::assertSentTo($lawyer, LawyerApplicationRejected::class);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $admin = $this->admin();
        $lawyer = $this->pendingLawyer();

        $response = $this->actingAs($admin)->post(route('admin.lawyer-verifications.reject', $lawyer->lawyerVerification), []);

        $response->assertSessionHasErrors('rejection_reason');
        $this->assertSame(VerificationStatus::PENDING, $lawyer->lawyerVerification->fresh()->status);
    }

    public function test_a_lawyer_cannot_approve_or_reject_any_application_including_their_own(): void
    {
        $lawyer = $this->pendingLawyer();
        $verification = $lawyer->lawyerVerification;

        $this->actingAs($lawyer)
            ->post(route('admin.lawyer-verifications.approve', $verification))
            ->assertForbidden();

        $this->actingAs($lawyer)
            ->post(route('admin.lawyer-verifications.reject', $verification), ['rejection_reason' => 'x'])
            ->assertForbidden();

        $this->assertSame(VerificationStatus::PENDING, $verification->fresh()->status);
    }

    public function test_a_customer_cannot_approve_or_reject_any_application(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $lawyer = $this->pendingLawyer();
        $verification = $lawyer->lawyerVerification;

        $this->actingAs($customer)
            ->post(route('admin.lawyer-verifications.approve', $verification))
            ->assertForbidden();

        $this->assertSame(VerificationStatus::PENDING, $verification->fresh()->status);
    }

    public function test_rejected_lawyers_remain_ineligible_and_non_public(): void
    {
        $admin = $this->admin();
        $lawyer = $this->pendingLawyer();

        $this->actingAs($admin)->post(route('admin.lawyer-verifications.reject', $lawyer->lawyerVerification), [
            'rejection_reason' => 'Documents did not match the application details provided.',
        ]);

        $this->assertFalse(
            LawyerProfile::searchable()->whereKey($lawyer->lawyerProfile->id)->exists()
        );
    }
}
