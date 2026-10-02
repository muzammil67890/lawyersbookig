<?php

namespace Tests\Feature\Messaging;

use App\Models\Conversation;
use App\Models\LawyerProfile;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8: server-side authorization and IDOR resistance for private
 * conversations (requirements #15, #16). Mirrors the authorization
 * sections of tests/Feature/Cases/LifecycleTest.php and
 * tests/Feature/Appointments/LifecycleTest.php.
 */
class AuthorizationTest extends TestCase
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

    public function test_customer_can_view_their_own_conversation(): void
    {
        [$conversation, $customer] = $this->conversation();

        $this->actingAs($customer)
            ->get(route('customer.messages.show', $conversation))
            ->assertOk();
    }

    public function test_lawyer_can_view_their_own_conversation(): void
    {
        [$conversation, , $lawyerUser] = $this->conversation();

        $this->actingAs($lawyerUser)
            ->get(route('lawyer.messages.show', $conversation))
            ->assertOk();
    }

    public function test_customer_cannot_view_another_customers_conversation(): void
    {
        [$conversation] = $this->conversation();
        $otherCustomer = User::factory()->create();

        $this->actingAs($otherCustomer)
            ->get(route('customer.messages.show', $conversation))
            ->assertForbidden();
    }

    public function test_lawyer_cannot_view_another_lawyers_conversation(): void
    {
        [$conversation] = $this->conversation();
        $otherLawyerUser = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->create(['user_id' => $otherLawyerUser->id]);

        $this->actingAs($otherLawyerUser)
            ->get(route('lawyer.messages.show', $conversation))
            ->assertForbidden();
    }

    public function test_a_customer_cannot_open_a_conversation_through_the_lawyer_route(): void
    {
        [$conversation, $customer] = $this->conversation();

        // role:lawyer middleware blocks a customer from /lawyer/* entirely,
        // even for a conversation they actually own.
        $this->actingAs($customer)
            ->get(route('lawyer.messages.show', $conversation))
            ->assertForbidden();
    }

    public function test_a_lawyer_cannot_open_a_conversation_through_the_customer_route(): void
    {
        [$conversation, , $lawyerUser] = $this->conversation();

        $this->actingAs($lawyerUser)
            ->get(route('customer.messages.show', $conversation))
            ->assertForbidden();
    }

    public function test_admin_cannot_view_a_private_conversation(): void
    {
        [$conversation] = $this->conversation();
        $admin = User::factory()->admin()->create();

        // Neither the customer nor lawyer message routes are reachable by
        // an admin account at all (role middleware), and even a direct
        // Gate check must refuse -- requirement #15: no hidden admin access.
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($admin)->allows('view', $conversation));
    }

    public function test_unrelated_lawyer_cannot_view_another_lawyers_conversation_list_entries(): void
    {
        [$conversation] = $this->conversation();
        $otherLawyerUser = User::factory()->lawyer()->create();
        LawyerProfile::factory()->approved()->create(['user_id' => $otherLawyerUser->id]);

        $response = $this->actingAs($otherLawyerUser)->get(route('lawyer.messages.index'));

        $response->assertOk();
        $response->assertDontSee($conversation->id);
    }

    public function test_customer_cannot_view_another_customers_message_via_message_policy(): void
    {
        [$conversation, , , $lawyer] = $this->conversation();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $lawyer->user_id,
        ]);
        $otherCustomer = User::factory()->create();

        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($otherCustomer)->allows('view', $message));
    }

    public function test_manipulating_the_conversation_id_in_the_url_cannot_access_someone_elses_messages(): void
    {
        [$conversationA] = $this->conversation();
        [$conversationB, $customerB] = $this->conversation();

        // customerB tries to walk conversation ids to reach conversationA.
        $this->actingAs($customerB)
            ->get('/customer/messages/'.$conversationA->id)
            ->assertForbidden();
    }
}
