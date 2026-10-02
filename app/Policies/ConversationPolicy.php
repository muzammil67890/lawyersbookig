<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\User;

/**
 * Phase 8: server-side authorization for private customer <-> lawyer
 * conversations, modeled directly on AppointmentPolicy / LegalCasePolicy.
 *
 * A conversation is private to exactly the two parties in it -- the
 * customer who started it and the lawyer they started it with.
 * Deliberately no admin access (requirement #15): private messages
 * stay private unless a future phase adds narrow, explicit oversight
 * the same deliberate way LegalCasePolicy does for cases. Ownership
 * itself is defined once, on the model (Conversation::isParticipant()),
 * not re-derived here.
 */
class ConversationPolicy
{
    /** A customer may start a conversation with an eligible lawyer; lawyers never initiate one. */
    public function create(User $user): bool
    {
        return $user->status === UserStatus::ACTIVE && $user->isCustomer();
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->status === UserStatus::ACTIVE && $conversation->isParticipant($user);
    }

    /** Generic "may act inside this conversation" ability -- sending a message, marking it read. */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
