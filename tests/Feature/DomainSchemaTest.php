<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Appointment;
use App\Models\CaseDocument;
use App\Models\Conversation;
use App\Models\LawyerProfile;
use App\Models\LawyerVerification;
use App\Models\LegalCase;
use App\Models\Message;
use App\Models\Review;
use App\Models\Service;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 database/domain-foundation tests. These exercise the
 * migrations, models and relationships described in the Phase 2
 * scope -- not any future booking/chat/AI workflow logic, which does
 * not exist yet.
 */
class DomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** 1. User roles can be represented correctly. */
    public function test_user_roles_are_represented_correctly(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $lawyer = User::factory()->create(['role' => 'lawyer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($customer->isCustomer());
        $this->assertTrue($lawyer->isLawyer());
        $this->assertTrue($admin->isAdmin());
        $this->assertSame(UserRole::LAWYER, $lawyer->role);
        $this->assertCount(1, User::query()->role(UserRole::ADMIN)->get());
    }

    /** 2. Lawyer profile belongs to a user. */
    public function test_lawyer_profile_belongs_to_a_user(): void
    {
        $user = User::factory()->lawyer()->create();
        $profile = LawyerProfile::factory()->for($user, 'user')->create();

        $this->assertTrue($profile->user->is($user));
        $this->assertTrue($user->lawyerProfile->is($profile));
    }

    /** 3. Lawyer verification belongs to the correct lawyer/user. */
    public function test_lawyer_verification_belongs_to_correct_user(): void
    {
        $lawyer = User::factory()->lawyer()->create();
        $admin = User::factory()->admin()->create();

        $verification = LawyerVerification::factory()->for($lawyer, 'user')->approved()->create([
            'reviewed_by' => $admin->id,
        ]);

        $this->assertTrue($verification->user->is($lawyer));
        $this->assertTrue($lawyer->lawyerVerification->is($verification));
        $this->assertTrue($verification->reviewer->is($admin));
        $this->assertTrue($admin->reviewedVerifications->contains($verification));
        $this->assertSame(VerificationStatus::APPROVED, $verification->status);
    }

    /** 4. Services can be related to lawyers. */
    public function test_services_relate_to_lawyers(): void
    {
        $profile = LawyerProfile::factory()->create();
        $service = Service::factory()->create();
        $specialization = Specialization::factory()->for($service)->create();

        $profile->services()->attach($service);
        $profile->specializations()->attach($specialization);

        $this->assertTrue($profile->services->contains($service));
        $this->assertTrue($service->lawyerProfiles->contains($profile));
        $this->assertTrue($profile->specializations->contains($specialization));
        $this->assertTrue($specialization->service->is($service));
    }

    /** 5. Appointment relationships are valid. */
    public function test_appointment_relationships_are_valid(): void
    {
        $customer = User::factory()->create();
        $profile = LawyerProfile::factory()->create();
        $service = Service::factory()->create();

        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $profile->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
        ]);

        $this->assertTrue($appointment->customer->is($customer));
        $this->assertTrue($appointment->lawyerProfile->is($profile));
        $this->assertTrue($appointment->service->is($service));
        $this->assertTrue($customer->appointmentsAsCustomer->contains($appointment));
        $this->assertTrue($profile->appointments->contains($appointment));
        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->status);

        // A user's lawyer-side appointments are reachable through the
        // has-many-through relationship on User.
        $this->assertTrue($profile->user->appointmentsAsLawyer->contains($appointment));
    }

    /** 6. Cases belong to customer and lawyer. */
    public function test_cases_belong_to_customer_and_lawyer(): void
    {
        $customer = User::factory()->create();
        $profile = LawyerProfile::factory()->create();

        $case = LegalCase::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $profile->id,
            'status' => 'active',
        ]);

        $this->assertTrue($case->customer->is($customer));
        $this->assertTrue($case->lawyerProfile->is($profile));
        $this->assertTrue($customer->casesAsCustomer->contains($case));
        $this->assertTrue($profile->user->casesAsLawyer->contains($case));
        $this->assertSame(CaseStatus::ACTIVE, $case->status);
    }

    /** 7. Case documents belong to cases. */
    public function test_case_documents_belong_to_cases(): void
    {
        $case = LegalCase::factory()->create();
        $uploader = User::factory()->create();

        $document = CaseDocument::factory()->for($case, 'case')->create([
            'uploaded_by' => $uploader->id,
        ]);

        $this->assertTrue($document->case->is($case));
        $this->assertTrue($case->documents->contains($document));
        $this->assertTrue($document->uploader->is($uploader));
    }

    /** 8. Conversations belong to customer/lawyer. */
    public function test_conversations_belong_to_customer_and_lawyer(): void
    {
        $customer = User::factory()->create();
        $profile = LawyerProfile::factory()->create();

        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $profile->id,
        ]);

        $this->assertTrue($conversation->customer->is($customer));
        $this->assertTrue($conversation->lawyerProfile->is($profile));
        $this->assertTrue($customer->conversations->contains($conversation));
        $this->assertTrue($profile->conversations->contains($conversation));
    }

    /** 9. Messages belong to conversations and sender. */
    public function test_messages_belong_to_conversation_and_sender(): void
    {
        $conversation = Conversation::factory()->create();
        $sender = User::factory()->create();

        $message = Message::factory()->for($conversation)->create([
            'sender_id' => $sender->id,
        ]);

        $this->assertTrue($message->conversation->is($conversation));
        $this->assertTrue($message->sender->is($sender));
        $this->assertTrue($conversation->messages->contains($message));
        $this->assertTrue($sender->sentMessages->contains($message));
    }

    /** 10. Reviews belong to the correct customer/lawyer relationship. */
    public function test_reviews_belong_to_correct_customer_and_lawyer(): void
    {
        $customer = User::factory()->create();
        $profile = LawyerProfile::factory()->create();
        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $profile->id,
        ]);

        $review = Review::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $profile->id,
            'appointment_id' => $appointment->id,
        ]);

        $this->assertTrue($review->customer->is($customer));
        $this->assertTrue($review->lawyerProfile->is($profile));
        $this->assertTrue($review->appointment->is($appointment));
        $this->assertTrue($customer->reviews->contains($review));
        $this->assertTrue($profile->reviews->contains($review));
    }

    /** 11. AI conversations/messages have correct relationships. */
    public function test_ai_conversations_and_messages_have_correct_relationships(): void
    {
        $customer = User::factory()->create();
        $conversation = AiConversation::factory()->for($customer, 'customer')->create();
        $message = AiMessage::factory()->for($conversation, 'conversation')->create(['sender' => 'assistant']);

        $this->assertTrue($conversation->customer->is($customer));
        $this->assertTrue($customer->aiConversations->contains($conversation));
        $this->assertTrue($message->conversation->is($conversation));
        $this->assertTrue($conversation->messages->contains($message));
    }

    /** 12. Foreign-key constraints work. */
    public function test_foreign_key_constraints_are_enforced(): void
    {
        $this->assertTrue(
            \DB::connection()->getPdo()->query('PRAGMA foreign_keys')->fetchColumn() == 1,
            'SQLite foreign key enforcement should be enabled for this test run.'
        );

        $this->expectException(QueryException::class);

        // No such user id exists, so this insert must fail the FK constraint.
        LawyerProfile::query()->create([
            'user_id' => 999999,
            'years_of_experience' => 1,
        ]);
    }

    /** 13. Important unique constraints work. */
    public function test_unique_constraints_are_enforced(): void
    {
        $user = User::factory()->create(['email' => 'unique@legalconnect.test']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'unique@legalconnect.test']);
    }

    public function test_lawyer_profile_user_id_is_unique(): void
    {
        $user = User::factory()->lawyer()->create();
        LawyerProfile::factory()->for($user, 'user')->create();

        $this->expectException(QueryException::class);

        LawyerProfile::factory()->for($user, 'user')->create();
    }

    public function test_review_cannot_duplicate_customer_and_appointment(): void
    {
        $customer = User::factory()->create();
        $appointment = Appointment::factory()->create(['customer_id' => $customer->id]);

        Review::factory()->create(['customer_id' => $customer->id, 'appointment_id' => $appointment->id]);

        $this->expectException(QueryException::class);

        Review::factory()->create(['customer_id' => $customer->id, 'appointment_id' => $appointment->id]);
    }

    /** 14. Database migrations run from a clean database (implicit). */
    public function test_migrations_produced_the_expected_domain_tables(): void
    {
        $expected = [
            'users', 'services', 'specializations', 'lawyer_profiles',
            'lawyer_verifications', 'lawyer_service', 'lawyer_specialization',
            'lawyer_availabilities', 'appointments', 'cases', 'case_documents',
            'conversations', 'messages', 'notifications', 'reviews',
            'ai_conversations', 'ai_messages',
        ];

        foreach ($expected as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Expected table [$table] to exist after migration.");
        }
    }
}
