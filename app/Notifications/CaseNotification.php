<?php

namespace App\Notifications;

use App\Enums\CaseEvent;
use App\Models\LegalCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the other party to a case that something happened to it
 * (assigned, reassigned, activated, progressed, completed, closed,
 * cancelled, document uploaded). Database channel only, matching the
 * existing appointment/lawyer-application notifications.
 *
 * The payload is deliberately minimal and contains nothing private: an
 * event name, a fixed message, the case id, case number, and title.
 * It never carries the case description, notes, cancellation reason,
 * or any document content or filesystem path. The recipient's own
 * dashboard resolves the id to a link, and that page is itself
 * authorization-protected by LegalCasePolicy.
 */
class CaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly LegalCase $case,
        private readonly CaseEvent $event,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'case_'.$this->event->value,
            'event' => $this->event->value,
            'message' => $this->event->message(),
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'title' => $this->case->title,
        ];
    }
}
