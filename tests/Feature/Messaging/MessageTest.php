<?php

namespace Tests\Feature\Messaging;

use App\Models\Conversation;
use App\Models\LawyerProfile;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8: message creation, sender-identity integrity, validation,
 * and stored-XSS resistance (requirements #11, #12, #13, #21).
 */
class MessageTest extends TestCase
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
            'last_message_at' => null,
        ]);

        return [$conversation, $customer, $lawyerUser, $lawyer];
    }

    public function test_customer_can_send_a_message(): void
    {
        [$conversation, $customer] = $this->conversation();

        $response = $this->actingAs($customer)->post(route('customer.messages.store', $conversation), [
            'body' => 'What documents do I need to bring?',
        ]);

        $message = Message::first();
        $this->assertNotNull($message);
        $this->assertSame($customer->id, $message->sender_id);
        $this->assertSame('What documents do I need to bring?', $message->body);
        $response->assertRedirect(route('customer.messages.show', $conversation));
        $this->assertNotNull($conversation->fresh()->last_message_at);
    }

    public function test_lawyer_can_send_a_message(): void
    {
        [$conversation, , $lawyerUser] = $this->conversation();

        $this->actingAs($lawyerUser)->post(route('lawyer.messages.store', $conversation), [
            'body' => 'Please bring your ID and the contract.',
        ]);

        $message = Message::first();
        $this->assertNotNull($message);
        $this->assertSame($lawyerUser->id, $message->sender_id);
    }

    public function test_message_sender_is_always_the_authenticated_user_even_if_a_sender_id_is_smuggled(): void
    {
        [$conversation, $customer, $lawyerUser] = $this->conversation();

        $this->actingAs($customer)->post(route('customer.messages.store', $conversation), [
            'body' => 'Hello',
            'sender_id' => $lawyerUser->id,
        ]);

        $message = Message::first();
        $this->assertSame($customer->id, $message->sender_id);
        $this->assertNotSame($lawyerUser->id, $message->sender_id);
    }

    public function test_a_smuggled_conversation_id_in_the_body_cannot_redirect_the_message_elsewhere(): void
    {
        [$conversation, $customer] = $this->conversation();
        $otherConversation = Conversation::factory()->create();

        $this->actingAs($customer)->post(route('customer.messages.store', $conversation), [
            'body' => 'Hello',
            'conversation_id' => $otherConversation->id,
        ]);

        $message = Message::first();
        $this->assertSame($conversation->id, $message->conversation_id);
    }

    public function test_a_customer_cannot_send_a_message_in_someone_elses_conversation(): void
    {
        [$conversation] = $this->conversation();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->post(route('customer.messages.store', $conversation), ['body' => 'Hello'])
            ->assertForbidden();

        $this->assertSame(0, Message::count());
    }

    public function test_a_lawyer_cannot_send_a_message_in_someone_elses_conversation(): void
    {
        [$conversation] = $this->conversation();
        $otherLawyerUser = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->create(['user_id' => $otherLawyerUser->id]);

        $this->actingAs($otherLawyerUser)
            ->post(route('lawyer.messages.store', $conversation), ['body' => 'Hello'])
            ->assertForbidden();

        $this->assertSame(0, Message::count());
    }

    public function test_an_empty_message_is_rejected(): void
    {
        [$conversation, $customer] = $this->conversation();

        $this->actingAs($customer)
            ->post(route('customer.messages.store', $conversation), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Message::count());
    }

    public function test_a_whitespace_only_message_is_rejected(): void
    {
        [$conversation, $customer] = $this->conversation();

        $this->actingAs($customer)
            ->post(route('customer.messages.store', $conversation), ['body' => "   \n\t  "])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Message::count());
    }

    public function test_an_oversized_message_is_rejected(): void
    {
        [$conversation, $customer] = $this->conversation();

        $this->actingAs($customer)
            ->post(route('customer.messages.store', $conversation), ['body' => str_repeat('a', 4001)])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Message::count());
    }

    public function test_a_message_at_the_max_length_is_accepted(): void
    {
        [$conversation, $customer] = $this->conversation();

        $this->actingAs($customer)
            ->post(route('customer.messages.store', $conversation), ['body' => str_repeat('a', 4000)])
            ->assertSessionDoesntHaveErrors('body');

        $this->assertSame(1, Message::count());
    }

    public function test_a_message_with_leading_and_trailing_whitespace_is_trimmed(): void
    {
        [$conversation, $customer] = $this->conversation();

        $this->actingAs($customer)->post(route('customer.messages.store', $conversation), [
            'body' => "  Hello there  \n",
        ]);

        $this->assertSame('Hello there', Message::first()->body);
    }

    public function test_sending_a_message_to_a_nonexistent_conversation_is_not_found(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post('/customer/messages/999999', ['body' => 'Hello'])
            ->assertNotFound();
    }

    public function test_a_message_containing_html_is_escaped_when_rendered_not_executed(): void
    {
        [$conversation, $customer] = $this->conversation();
        $payload = '<script>alert(1)</script>';

        $this->actingAs($customer)->post(route('customer.messages.store', $conversation), ['body' => $payload]);

        // The raw payload is stored as literal text...
        $this->assertSame($payload, Message::first()->body);

        // ...but the chat view never renders it as executable markup: Blade's
        // {{ }} escaping turns it into inert, HTML-encoded text.
        $response = $this->actingAs($customer)->get(route('customer.messages.show', $conversation));
        $response->assertOk();
        $response->assertDontSee($payload, false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
