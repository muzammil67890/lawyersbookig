<?php

namespace Tests\Feature\Cases;

use App\Enums\CaseStatus;
use App\Models\CaseDocument;
use App\Models\LawyerProfile;
use App\Models\LegalCase;
use App\Models\User;
use App\Notifications\CaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 7: secure case document upload/download/delete -- private
 * storage, MIME/extension/size validation, safe filenames, IDOR
 * protection on both download and delete, and metadata exposure.
 */
class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function pendingCaseWithParties(): array
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $customer = User::factory()->create();

        $case = LegalCase::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
            'status' => CaseStatus::PENDING->value,
        ]);

        return [$case, $customer, $lawyerUser, $lawyer];
    }

    // -- Upload -------------------------------------------------------------

    public function test_customer_can_upload_a_valid_document_to_their_case(): void
    {
        Notification::fake();
        [$case, $customer, $lawyerUser] = $this->pendingCaseWithParties();
        $file = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');

        $this->actingAs($customer)
            ->post(route('customer.cases.documents.store', $case), ['document' => $file, 'category' => 'contract'])
            ->assertRedirect();

        $document = CaseDocument::first();
        $this->assertNotNull($document);
        $this->assertSame($case->id, $document->case_id);
        $this->assertSame($customer->id, $document->uploaded_by);
        $this->assertSame('contract.pdf', $document->original_filename);
        Notification::assertSentTo($lawyerUser, CaseNotification::class);
    }

    public function test_uploaded_file_is_stored_under_a_generated_name_not_the_original_filename(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $file = UploadedFile::fake()->create('../../../etc/passwd.pdf', 10, 'application/pdf');

        $this->actingAs($customer)
            ->post(route('customer.cases.documents.store', $case), ['document' => $file]);

        $document = CaseDocument::first();
        $this->assertStringNotContainsString('..', $document->stored_path);
        $this->assertStringNotContainsString('passwd', $document->stored_path);
        Storage::disk('local')->assertExists($document->stored_path);
    }

    public function test_lawyer_can_upload_a_document_to_an_assigned_case(): void
    {
        [$case, , $lawyerUser] = $this->pendingCaseWithParties();
        $file = UploadedFile::fake()->image('evidence.jpg');

        $this->actingAs($lawyerUser)
            ->post(route('lawyer.cases.documents.store', $case), ['document' => $file, 'category' => 'evidence'])
            ->assertRedirect();

        $this->assertDatabaseHas('case_documents', ['case_id' => $case->id, 'uploaded_by' => $lawyerUser->id]);
    }

    public function test_an_unrelated_lawyer_cannot_upload_to_a_case(): void
    {
        [$case] = $this->pendingCaseWithParties();
        $otherLawyerUser = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->create(['user_id' => $otherLawyerUser->id]);
        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $this->actingAs($otherLawyerUser)
            ->post(route('lawyer.cases.documents.store', $case), ['document' => $file])
            ->assertForbidden();

        $this->assertDatabaseCount('case_documents', 0);
    }

    public function test_upload_is_rejected_for_a_disallowed_extension(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $file = UploadedFile::fake()->create('shell.php', 10, 'application/x-php');

        $this->actingAs($customer)
            ->post(route('customer.cases.documents.store', $case), ['document' => $file])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('case_documents', 0);
    }

    public function test_upload_is_rejected_for_an_executable_disguised_with_an_allowed_extension(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        // Actual ELF magic bytes with a ".pdf" filename: the mimes
        // rule validates the file's real detected content type, not
        // the client-supplied filename extension.
        $file = UploadedFile::fake()->createWithContent('malware.pdf', "\x7FELF".str_repeat('A', 40));

        $this->actingAs($customer)
            ->post(route('customer.cases.documents.store', $case), ['document' => $file])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('case_documents', 0);
    }

    public function test_upload_is_rejected_when_it_exceeds_the_size_limit(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $file = UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf'); // 20MB > 10MB cap

        $this->actingAs($customer)
            ->post(route('customer.cases.documents.store', $case), ['document' => $file])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('case_documents', 0);
    }

    public function test_upload_is_rejected_for_an_invalid_category(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $this->actingAs($customer)
            ->post(route('customer.cases.documents.store', $case), ['document' => $file, 'category' => 'not-a-real-category'])
            ->assertSessionHasErrors('category');
    }

    public function test_upload_is_rejected_once_the_case_is_closed(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $case->update(['status' => CaseStatus::CLOSED->value]);
        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $this->actingAs($customer)
            ->post(route('customer.cases.documents.store', $case), ['document' => $file])
            ->assertForbidden();

        $this->assertDatabaseCount('case_documents', 0);
    }

    // -- Download / IDOR ------------------------------------------------------

    public function test_customer_can_download_their_own_case_document(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);
        Storage::disk('local')->put($document->stored_path, 'fake-pdf-content');

        $this->actingAs($customer)
            ->get(route('customer.cases.documents.download', [$case, $document]))
            ->assertOk();
    }

    public function test_customer_a_cannot_download_customer_bs_document(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);
        Storage::disk('local')->put($document->stored_path, 'content');

        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->get(route('customer.cases.documents.download', [$case, $document]))
            ->assertForbidden();
    }

    public function test_lawyer_a_cannot_download_lawyer_bs_case_document(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);
        Storage::disk('local')->put($document->stored_path, 'content');

        $otherLawyerUser = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->create(['user_id' => $otherLawyerUser->id]);

        $this->actingAs($otherLawyerUser)
            ->get(route('lawyer.cases.documents.download', [$case, $document]))
            ->assertForbidden();
    }

    public function test_an_unrelated_user_cannot_download_any_private_document(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);
        Storage::disk('local')->put($document->stored_path, 'content');

        $unrelated = User::factory()->create();

        $this->actingAs($unrelated)
            ->get(route('customer.cases.documents.download', [$case, $document]))
            ->assertForbidden();
    }

    public function test_changing_the_document_id_in_the_url_cannot_bypass_authorization(): void
    {
        [$caseA, $customerA] = $this->pendingCaseWithParties();
        [$caseB, $customerB] = $this->pendingCaseWithParties();

        $documentB = CaseDocument::factory()->for($caseB, 'case')->create(['uploaded_by' => $customerB->id]);
        Storage::disk('local')->put($documentB->stored_path, 'content');

        // customerA is authorized on caseA, but not on documentB (which
        // belongs to caseB) -- neither the case-mismatch check nor the
        // policy should let this through.
        $this->actingAs($customerA)
            ->get(route('customer.cases.documents.download', [$caseA, $documentB]))
            ->assertNotFound();
    }

    public function test_admin_can_download_any_case_document(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);
        Storage::disk('local')->put($document->stored_path, 'content');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.cases.documents.download', [$case, $document]))
            ->assertOk();
    }

    public function test_a_document_missing_from_disk_returns_not_found_not_a_server_error(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);
        // Deliberately never written to the fake disk.

        $this->actingAs($customer)
            ->get(route('customer.cases.documents.download', [$case, $document]))
            ->assertNotFound();
    }

    // -- Delete ---------------------------------------------------------------

    public function test_uploader_can_delete_their_own_document(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);
        Storage::disk('local')->put($document->stored_path, 'content');

        $this->actingAs($customer)
            ->delete(route('customer.cases.documents.destroy', [$case, $document]))
            ->assertRedirect();

        $this->assertDatabaseMissing('case_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->stored_path);
    }

    public function test_a_customer_cannot_delete_a_document_the_lawyer_uploaded_on_their_own_case(): void
    {
        [$case, $customer, $lawyerUser] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $lawyerUser->id]);
        Storage::disk('local')->put($document->stored_path, 'content');

        $this->actingAs($customer)
            ->delete(route('customer.cases.documents.destroy', [$case, $document]))
            ->assertForbidden();

        $this->assertDatabaseHas('case_documents', ['id' => $document->id]);
    }

    public function test_a_lawyer_cannot_delete_the_customers_document_on_their_own_case(): void
    {
        [$case, $customer, $lawyerUser] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);
        Storage::disk('local')->put($document->stored_path, 'content');

        $this->actingAs($lawyerUser)
            ->delete(route('lawyer.cases.documents.destroy', [$case, $document]))
            ->assertForbidden();

        $this->assertDatabaseHas('case_documents', ['id' => $document->id]);
    }

    public function test_admin_can_delete_any_document(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);
        Storage::disk('local')->put($document->stored_path, 'content');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.cases.documents.destroy', [$case, $document]))
            ->assertRedirect();

        $this->assertDatabaseMissing('case_documents', ['id' => $document->id]);
    }

    public function test_deleting_a_document_record_still_succeeds_even_if_the_file_is_already_gone_from_disk(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);
        // Never written to disk -- simulates a file already missing.

        $this->actingAs($customer)
            ->delete(route('customer.cases.documents.destroy', [$case, $document]))
            ->assertRedirect();

        $this->assertDatabaseMissing('case_documents', ['id' => $document->id]);
    }

    public function test_an_unrelated_user_cannot_delete_a_document_by_manipulating_the_id(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);
        Storage::disk('local')->put($document->stored_path, 'content');
        $unrelated = User::factory()->create();

        $this->actingAs($unrelated)
            ->delete(route('customer.cases.documents.destroy', [$case, $document]))
            ->assertForbidden();

        $this->assertDatabaseHas('case_documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->stored_path);
    }

    // -- Metadata exposure ------------------------------------------------

    public function test_stored_path_is_never_serialized(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create(['uploaded_by' => $customer->id]);

        $this->assertArrayNotHasKey('stored_path', $document->toArray());
    }

    public function test_the_case_show_page_never_prints_the_raw_storage_path(): void
    {
        [$case, $customer] = $this->pendingCaseWithParties();
        $document = CaseDocument::factory()->for($case, 'case')->create([
            'uploaded_by' => $customer->id,
            'stored_path' => 'case-documents/'.$case->id.'/super-secret-uuid-name.pdf',
        ]);
        Storage::disk('local')->put($document->stored_path, 'content');

        $this->actingAs($customer)
            ->get(route('customer.cases.show', $case))
            ->assertOk()
            ->assertDontSee('super-secret-uuid-name.pdf');
    }
}
