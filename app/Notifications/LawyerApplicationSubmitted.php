<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to every admin when a lawyer completes onboarding and their
 * application enters the review queue. Database-only: this platform
 * has no admin-facing mail template yet, and the in-app notification
 * bell (via the `Notifiable` trait already on `User`) is enough to
 * surface "something needs review" without inventing new mail
 * infrastructure for Phase 4.
 */
class LawyerApplicationSubmitted extends Notification
{
    use Queueable;

    public function __construct(protected User $lawyer)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lawyer_application_submitted',
            'lawyer_id' => $this->lawyer->id,
            'lawyer_name' => $this->lawyer->name,
            'message' => "{$this->lawyer->name} submitted a lawyer verification application for review.",
        ];
    }
}
