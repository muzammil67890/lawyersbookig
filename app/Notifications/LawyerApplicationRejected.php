<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a lawyer when an admin rejects their verification
 * application. Carries the rejection reason so the in-app
 * notification is self-contained even before the lawyer opens their
 * dashboard. Database-only -- see LawyerApplicationSubmitted for why
 * mail is out of scope here.
 */
class LawyerApplicationRejected extends Notification
{
    use Queueable;

    public function __construct(protected string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lawyer_application_rejected',
            'reason' => $this->reason,
            'message' => 'Your lawyer verification application was rejected: '.$this->reason,
        ];
    }
}
