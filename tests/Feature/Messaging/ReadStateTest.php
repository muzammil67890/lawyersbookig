<?php

namespace Tests\Feature\Messaging;

use App\Models\Conversation;
use App\Models\LawyerProfile;
use App\Models\Message;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8: read/unread state (requirement #22) -- unread counts,
 * mark-as-read on viewing a conversation, and that one participant can
 * never mark the other participant's messages as read.
 */
class ReadStateTest extends TestCase
{
    use RefreshDatabase;

    protected function conversation(): array
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $customer = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
        ]);

        return [$conversation, $customer, $lawyerUser, $lawyer];
    }

    public function test_a_new_message_is_unread_for_the_recipient(): void
    {
        [$conversation, $customer, $lawyerUser] = $this->conversation();
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
        ]);

        $service = app(MessagingService::class);

        $this->assertSame(1, $service->totalUnreadCount($lawyerUser));
        $this->assertSame(0, $service->totalUnreadCount($customer));
    }

    public function test_opening_a_conversation_marks_the_other_partys_messages_as_read(): void
    {
        [$conversation, $customer, $lawyerUser] = $this->conversation();
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
        ]);

        $this->actingAs($lawyerUser)->get(route('lawyer.messages.show', $conversation))->assertOk();

        $this->assertNotNull(Message::first()->read_at);
    }

    public function test_opening_a_conversation_never_marks_the_viewers_own_messages_as_read(): void
    {
        [$conversation, $customer] = $this->conversation();
        $ownMessage = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
        ]);

        $this->actingAs($customer)->get(route('customer.messages.show', $conversation));

        // read_at tracks "has the OTHER party read this", so the sender's
        // own message must stay unread by this mechanism.
        $this->assertNull($ownMessage->fresh()->read_at);
    }

    public function test_a_user_who_is_not_a_participant_cannot_mark_someone_elses_messages_as_read(): void
    {
        [$conversation, $customer] = $this->conversation();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
        ]);
        $unrelatedUser = User::factory()->create();

        app(MessagingService::class)->markConversationRead($unrelatedUser, $conversation);

        $this->assertNull($message->fresh()->read_at);
    }

    public function test_total_unread_count_sums_across_multiple_conversations(): void
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        $conversationA = Conversation::factory()->create(['customer_id' => $customerA->id, 'lawyer_profile_id' => $lawyer->id]);
        $conversationB = Conversation::factory()->create(['customer_id' => $customerB->id, 'lawyer_profile_id' => $lawyer->id]);

        Message::factory()->count(2)->create(['conversation_id' => $conversationA->id, 'sender_id' => $customerA->id]);
        Message::factory()->count(3)->create(['conversation_id' => $conversationB->id, 'sender_id' => $customerB->id]);

        $this->assertSame(5, app(MessagingService::class)->totalUnreadCount($lawyerUser));
    }

    public function test_a_read_message_does_not_count_as_unread(): void
    {
        [$conversation, $customer, $lawyerUser] = $this->conversation();
        Message::factory()->read()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
        ]);

        $this->assertSame(0, app(MessagingService::class)->totalUnreadCount($lawyerUser));
    }

    public function test_unread_badge_appears_on_the_lawyer_dashboard(): void
    {
        [$conversation, $customer, $lawyerUser] = $this->conversation();
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $customer->id]);

        $this->actingAs($lawyerUser)
            ->get(route('lawyer.dashboard'))
            ->assertOk()
            ->assertSee('class="unread"', false);
    }
}
