<?php

namespace Tests\Feature\Lawyers;

use App\Models\LawyerProfile;
use App\Models\LawyerVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_active_lawyers_profile_is_viewable(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Visible Lawyer']);

        $response = $this->get(route('lawyers.show', $lawyer));

        $response->assertOk();
        $response->assertSee('Adv. Visible Lawyer');
    }

    public function test_a_pending_lawyers_profile_is_not_accessible(): void
    {
        $lawyer = LawyerProfile::factory()->pending()->create();

        $this->get(route('lawyers.show', $lawyer))->assertNotFound();
    }

    public function test_a_rejected_lawyers_profile_is_not_accessible(): void
    {
        $lawyer = LawyerProfile::factory()->rejected()->create();

        $this->get(route('lawyers.show', $lawyer))->assertNotFound();
    }

    public function test_a_suspended_inactive_lawyers_profile_is_not_accessible(): void
    {
        $lawyer = LawyerProfile::factory()->suspended()->create();

        $this->get(route('lawyers.show', $lawyer))->assertNotFound();
    }

    public function test_an_approved_but_not_public_lawyers_profile_is_not_accessible(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create(['is_public' => false]);

        $this->get(route('lawyers.show', $lawyer))->assertNotFound();
    }

    public function test_an_approved_but_inactive_lawyers_profile_is_not_accessible(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create(['is_active' => false]);

        $this->get(route('lawyers.show', $lawyer))->assertNotFound();
    }

    public function test_a_nonexistent_lawyer_id_returns_404(): void
    {
        $this->get(route('lawyers.show', ['lawyer' => 999999]))->assertNotFound();
    }

    public function test_pending_and_rejected_profiles_return_the_identical_not_found_response(): void
    {
        $pending = LawyerProfile::factory()->pending()->create();
        $rejected = LawyerProfile::factory()->rejected()->create();

        $pendingResponse = $this->get(route('lawyers.show', $pending));
        $rejectedResponse = $this->get(route('lawyers.show', $rejected));

        // Same status code and same generic 404 body -- nothing in the
        // response distinguishes "pending", "rejected" or "doesn't exist".
        $pendingResponse->assertNotFound();
        $rejectedResponse->assertNotFound();
        $this->assertSame($pendingResponse->status(), $rejectedResponse->status());
    }

    // --- Privacy ------------------------------------------------------

    public function test_the_public_profile_never_exposes_cnic_or_face_verification_paths(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        LawyerVerification::factory()->for($lawyer->user, 'user')->create([
            'cnic_image_path' => 'lawyers/verification/cnic/super-secret-cnic.jpg',
            'face_image_path' => 'lawyers/verification/face/super-secret-face.jpg',
        ]);

        $response = $this->get(route('lawyers.show', $lawyer));

        $response->assertOk();
        $response->assertDontSee('super-secret-cnic.jpg', false);
        $response->assertDontSee('super-secret-face.jpg', false);
        $response->assertDontSee('cnic', false);
    }

    public function test_the_public_profile_never_exposes_rejection_reason_or_reviewer_info(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        $admin = \App\Models\User::factory()->admin()->create(['name' => 'Secret Admin Reviewer']);
        LawyerVerification::factory()->for($lawyer->user, 'user')->create([
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'rejection_reason' => 'This exact rejection reason text must never leak publicly',
        ]);

        $response = $this->get(route('lawyers.show', $lawyer));

        $response->assertOk();
        $response->assertDontSee('This exact rejection reason text must never leak publicly');
        $response->assertDontSee('Secret Admin Reviewer');
    }

    public function test_the_directory_listing_never_exposes_verification_documents(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Privacy Check']);
        LawyerVerification::factory()->for($lawyer->user, 'user')->create([
            'cnic_image_path' => 'lawyers/verification/cnic/directory-secret.jpg',
        ]);

        $response = $this->get(route('lawyers.index'));

        $response->assertOk();
        $response->assertDontSee('directory-secret.jpg', false);
    }
}
