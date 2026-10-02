<?php

namespace Tests\Feature\Messaging;

use App\Models\Conversation;
use App\Models\LawyerProfile;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Phase 8: messaging notifications go to the correct (and only the
 * correct) recipient, never to the sender, and never leak the message
 * body in the notification payload (requirement #23, #31).
 */
class NotificationTest extends TestCase
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

        return [$conversation, $customer, $lawyerUser];
    }

    public function test_lawyer_is_notified_when_a_customer_sends_a_message(): void
    {
        Notification::fake();
        [$conversation, $customer, $lawyerUser] = $this->conversation();

        $this->actingAs($customer)->post(route('customer.messages.store', $conversation), [
            'body' => 'Are you available this week?',
        ]);

        Notification::assertSentTo($lawyerUser, NewMessageNotification::class);
        Notification::assertNotSentTo($customer, NewMessageNotification::class);
    }

    public function test_customer_is_notified_when_a_lawyer_sends_a_message(): void
    {
        Notification::fake();
        [$conversation, $customer, $lawyerUser] = $this->conversation();

        $this->actingAs($lawyerUser)->post(route('lawyer.messages.store', $conversation), [
            'body' => 'Yes, Tuesday afternoon works.',
        ]);

        Notification::assertSentTo($customer, NewMessageNotification::class);
        Notification::assertNotSentTo($lawyerUser, NewMessageNotification::class);
    }

    public function test_notification_payload_never_contains_the_message_body(): void
    {
        Notification::fake();
        [$conversation, $customer, $lawyerUser] = $this->conversation();
        $secret = 'My case involves a confidential settlement of $500,000.';

        $this->actingAs($customer)->post(route('customer.messages.store', $conversation), ['body' => $secret]);

        Notification::assertSentTo($lawyerUser, NewMessageNotification::class, function ($notification, $channels, $notifiable) use ($secret) {
            $data = $notification->toArray($notifiable);

            return ! str_contains(json_encode($data), $secret);
        });
    }

    public function test_no_third_party_is_ever_notified(): void
    {
        Notification::fake();
        [$conversation, $customer] = $this->conversation();
        $unrelatedUser = User::factory()->create();

        $this->actingAs($customer)->post(route('customer.messages.store', $conversation), ['body' => 'Hello']);

        Notification::assertNotSentTo($unrelatedUser, NewMessageNotification::class);
    }
}
