<?php

namespace Tests\Feature\Messaging;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\LawyerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8: conversation creation, reuse, duplicate prevention, and
 * lawyer-eligibility enforcement. Mirrors
 * tests/Feature/Cases/LifecycleTest.php's creation section.
 */
class ConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function approvedLawyer(): array
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);

        return [$lawyerUser, $lawyer];
    }

    public function test_customer_can_start_a_conversation_with_an_eligible_lawyer(): void
    {
        [, $lawyer] = $this->approvedLawyer();
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->post(route('customer.messages.start', $lawyer->id));

        $conversation = Conversation::first();
        $this->assertNotNull($conversation);
        $this->assertSame($customer->id, $conversation->customer_id);
        $this->assertSame($lawyer->id, $conversation->lawyer_profile_id);
        $this->assertSame(ConversationStatus::ACTIVE, $conversation->status);
        $response->assertRedirect(route('customer.messages.show', $conversation));
    }

    public function test_starting_a_conversation_twice_reuses_the_existing_one(): void
    {
        [, $lawyer] = $this->approvedLawyer();
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('customer.messages.start', $lawyer->id));
        $this->actingAs($customer)->post(route('customer.messages.start', $lawyer->id));

        $this->assertSame(1, Conversation::count());
    }

    public function test_two_different_customers_get_two_separate_conversations_with_the_same_lawyer(): void
    {
        [, $lawyer] = $this->approvedLawyer();
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        $this->actingAs($customerA)->post(route('customer.messages.start', $lawyer->id));
        $this->actingAs($customerB)->post(route('customer.messages.start', $lawyer->id));

        $this->assertSame(2, Conversation::count());
    }

    public function test_duplicate_conversation_is_rejected_at_the_database_level(): void
    {
        [, $lawyer] = $this->approvedLawyer();
        $customer = User::factory()->create();

        Conversation::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Conversation::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
        ]);
    }

    public function test_customer_cannot_message_a_pending_lawyer(): void
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->pending()->create(['user_id' => $lawyerUser->id]);
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('customer.messages.start', $lawyer->id))
            ->assertNotFound();

        $this->assertSame(0, Conversation::count());
    }

    public function test_customer_cannot_message_a_rejected_lawyer(): void
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->rejected()->create(['user_id' => $lawyerUser->id]);
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('customer.messages.start', $lawyer->id))
            ->assertNotFound();

        $this->assertSame(0, Conversation::count());
    }

    public function test_customer_cannot_message_a_suspended_lawyer(): void
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->suspended()->create(['user_id' => $lawyerUser->id]);
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('customer.messages.start', $lawyer->id))
            ->assertNotFound();

        $this->assertSame(0, Conversation::count());
    }

    public function test_customer_cannot_message_an_approved_lawyer_whose_account_is_suspended(): void
    {
        $lawyerUser = User::factory()->lawyer()->suspended()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('customer.messages.start', $lawyer->id))
            ->assertNotFound();

        $this->assertSame(0, Conversation::count());
    }

    public function test_customer_cannot_message_an_approved_but_privately_unlisted_lawyer(): void
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create([
            'user_id' => $lawyerUser->id,
            'is_public' => false,
        ]);
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('customer.messages.start', $lawyer->id))
            ->assertNotFound();
    }

    public function test_a_lawyer_cannot_start_a_conversation(): void
    {
        [, $lawyer] = $this->approvedLawyer();
        $someLawyerUser = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->create(['user_id' => $someLawyerUser->id]);

        $this->actingAs($someLawyerUser)
            ->post(route('customer.messages.start', $lawyer->id))
            ->assertForbidden(); // role:customer middleware blocks a lawyer from /customer/* entirely
    }

    public function test_starting_a_conversation_with_a_nonexistent_lawyer_is_not_found(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('customer.messages.start', 999999))
            ->assertNotFound();
    }
}
