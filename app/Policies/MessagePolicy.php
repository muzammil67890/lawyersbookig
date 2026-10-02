<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 8: a message's access rules are entirely inherited from the
 * conversation it belongs to -- rather than re-deriving "is this user
 * a party to this conversation" a second time here, this delegates to
 * ConversationPolicy through Gate::forUser(), mirroring
 * CaseDocumentPolicy's relationship to LegalCasePolicy, so membership
 * is defined in exactly one place.
 */
class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return $message->conversation !== null
            && Gate::forUser($user)->allows('view', $message->conversation);
    }
}
