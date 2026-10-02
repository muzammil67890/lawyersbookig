<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a lawyer when an admin approves their verification
 * application. Database-only, delivered via the `Notifiable` trait
 * already on `User` -- see LawyerApplicationSubmitted for why mail is
 * out of scope here.
 */
class LawyerApplicationApproved extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lawyer_application_approved',
            'message' => 'Your lawyer verification application has been approved.',
        ];
    }
}
