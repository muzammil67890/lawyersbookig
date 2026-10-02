<?php

namespace Tests\Feature\Messaging;

use App\Models\Conversation;
use App\Models\LawyerProfile;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8: conversation-list scoping, pagination, and defense against
 * smuggled ownership/status fields on conversation creation
 * (requirement #14: mass assignment security).
 */
class UiTest extends TestCase
{
    use RefreshDatabase;

    protected function approvedLawyer(): array
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);

        return [$lawyerUser, $lawyer];
    }

    public function test_customer_message_list_only_shows_their_own_conversations(): void
    {
        [, $lawyer] = $this->approvedLawyer();
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();

        $mine = Conversation::factory()->create(['customer_id' => $customer->id, 'lawyer_profile_id' => $lawyer->id]);
        Conversation::factory()->create(['customer_id' => $otherCustomer->id, 'lawyer_profile_id' => $lawyer->id]);

        $response = $this->actingAs($customer)->get(route('customer.messages.index'));

        $response->assertOk();
        $response->assertViewHas('conversations', function ($conversations) use ($mine) {
            return $conversations->count() === 1 && $conversations->first()->is($mine);
        });
    }

    public function test_lawyer_message_list_only_shows_their_own_conversations(): void
    {
        [$lawyerUser, $lawyer] = $this->approvedLawyer();
        [$otherLawyerUser, $otherLawyer] = $this->approvedLawyer();
        $customer = User::factory()->create();

        $mine = Conversation::factory()->create(['customer_id' => $customer->id, 'lawyer_profile_id' => $lawyer->id]);
        Conversation::factory()->create(['customer_id' => $customer->id, 'lawyer_profile_id' => $otherLawyer->id]);

        $response = $this->actingAs($lawyerUser)->get(route('lawyer.messages.index'));

        $response->assertOk();
        $response->assertViewHas('conversations', function ($conversations) use ($mine) {
            return $conversations->count() === 1 && $conversations->first()->is($mine);
        });
    }

    public function test_message_list_is_paginated(): void
    {
        [, $lawyer] = $this->approvedLawyer();
        $customer = User::factory()->create();
        Conversation::factory()->count(20)->create(['customer_id' => $customer->id, 'lawyer_profile_id' => $lawyer->id]);

        $response = $this->actingAs($customer)->get(route('customer.messages.index'));

        $response->assertViewHas('conversations', fn ($conversations) => $conversations->perPage() === 15 && $conversations->total() === 20);
    }

    public function test_conversation_messages_are_paginated(): void
    {
        [, $lawyer] = $this->approvedLawyer();
        $customer = User::factory()->create();
        $conversation = Conversation::factory()->create(['customer_id' => $customer->id, 'lawyer_profile_id' => $lawyer->id]);
        Message::factory()->count(45)->create(['conversation_id' => $conversation->id, 'sender_id' => $customer->id]);

        $response = $this->actingAs($customer)->get(route('customer.messages.show', $conversation));

        $response->assertViewHas('messages', fn ($messages) => $messages->perPage() === 30 && $messages->total() === 45);
    }

    public function test_starting_a_conversation_ignores_smuggled_status_and_ownership_fields(): void
    {
        [, $lawyer] = $this->approvedLawyer();
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();

        $this->actingAs($customer)->post(route('customer.messages.start', $lawyer->id), [
            'customer_id' => $otherCustomer->id,
            'lawyer_profile_id' => 999999,
            'status' => 'closed',
            'last_message_at' => now()->subYear(),
        ]);

        $conversation = Conversation::first();
        $this->assertSame($customer->id, $conversation->customer_id);
        $this->assertSame($lawyer->id, $conversation->lawyer_profile_id);
        $this->assertSame('active', $conversation->status->value);
    }

    public function test_an_unauthenticated_visitor_is_redirected_to_login(): void
    {
        [, $lawyer] = $this->approvedLawyer();

        $this->post(route('customer.messages.start', $lawyer->id))
            ->assertRedirect(route('login'));
    }
}
