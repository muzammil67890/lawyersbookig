<?php

namespace Tests\Feature\Lawyer;

use App\Models\LawyerVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificationDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function verificationWithStoredFiles(User $lawyer): LawyerVerification
    {
        Storage::fake('local');

        Storage::disk('local')->put('lawyers/verification/cnic/fake.jpg', 'cnic-bytes');
        Storage::disk('local')->put('lawyers/verification/face/fake.jpg', 'face-bytes');

        return LawyerVerification::factory()->for($lawyer, 'user')->create([
            'cnic_image_path' => 'lawyers/verification/cnic/fake.jpg',
            'face_image_path' => 'lawyers/verification/face/fake.jpg',
        ]);
    }

    public function test_a_lawyer_can_view_their_own_cnic_and_face_documents(): void
    {
        $lawyer = User::factory()->lawyer()->create(['email_verified_at' => now()]);
        $this->verificationWithStoredFiles($lawyer);

        $this->actingAs($lawyer)->get(route('lawyer.verification.document', ['type' => 'cnic']))->assertOk();
        $this->actingAs($lawyer)->get(route('lawyer.verification.document', ['type' => 'face']))->assertOk();
    }

    public function test_a_lawyer_without_a_verification_record_gets_404(): void
    {
        $lawyer = User::factory()->lawyer()->create(['email_verified_at' => now()]);

        $this->actingAs($lawyer)->get(route('lawyer.verification.document', ['type' => 'cnic']))->assertNotFound();
    }

    public function test_a_customer_cannot_reach_the_lawyer_document_route_at_all(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->get(route('lawyer.verification.document', ['type' => 'cnic']))->assertForbidden();
    }

    public function test_a_lawyer_cannot_view_another_lawyers_documents_via_the_admin_route(): void
    {
        $lawyerA = User::factory()->lawyer()->create(['email_verified_at' => now()]);
        $lawyerB = User::factory()->lawyer()->create(['email_verified_at' => now()]);
        $verification = $this->verificationWithStoredFiles($lawyerA);

        // The lawyer-scoped route can't even resolve another lawyer's
        // verification record; the admin-shaped route with an explicit
        // ID is the one that must be authorization-checked, not
        // resolvable via a bare 404 from a missing relationship.
        $this->actingAs($lawyerB)
            ->get(route('admin.lawyer-verifications.document', [$verification, 'cnic']))
            ->assertForbidden();
    }

    public function test_a_customer_cannot_view_a_lawyers_documents_via_the_admin_route(): void
    {
        $lawyer = User::factory()->lawyer()->create(['email_verified_at' => now()]);
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $verification = $this->verificationWithStoredFiles($lawyer);

        $this->actingAs($customer)
            ->get(route('admin.lawyer-verifications.document', [$verification, 'cnic']))
            ->assertForbidden();
    }

    public function test_an_admin_can_view_any_lawyers_documents(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $lawyer = User::factory()->lawyer()->create(['email_verified_at' => now()]);
        $verification = $this->verificationWithStoredFiles($lawyer);

        $this->actingAs($admin)
            ->get(route('admin.lawyer-verifications.document', [$verification, 'cnic']))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.lawyer-verifications.document', [$verification, 'face']))
            ->assertOk();
    }

    public function test_an_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('lawyer.verification.document', ['type' => 'cnic']))
            ->assertRedirect(route('login'));
    }
}
