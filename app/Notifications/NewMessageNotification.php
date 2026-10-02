<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the OTHER participant in a private conversation that a new
 * message has arrived (Phase 8, requirement #23). Database channel
 * only, matching every other Legal Connect notification -- no mail
 * infrastructure required.
 *
 * The payload is deliberately minimal: it never carries the message
 * body, only who sent it and which conversation to open. Notification
 * data is a lower-trust surface than the authorization-protected
 * conversation view itself, so private message content must never
 * leak through it. MessagingService is the only place that dispatches
 * this, and it always resolves the recipient from
 * Conversation::otherPartyFor() -- never from request input -- and
 * never sends it to the sender themselves.
 */
class NewMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Conversation $conversation,
        private readonly User $sender,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_message',
            'message' => 'You have a new message from '.$this->sender->name.'.',
            'conversation_id' => $this->conversation->id,
        ];
    }
}
