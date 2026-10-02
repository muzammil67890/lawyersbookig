<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Phase 9: tells a lawyer a customer has left a review, mirroring
 * AppointmentNotification's shape (database channel only, minimal
 * payload). The payload deliberately never carries the review's
 * comment text or the reviewing customer's contact details -- only the
 * rating, the review id, and enough context to link to it. A future
 * mail channel could safely reuse this same toArray() shape.
 */
class ReviewNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Review $review)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'review_submitted',
            'message' => 'A customer left a '.$this->review->rating.'-star review.',
            'review_id' => $this->review->id,
            'rating' => $this->review->rating,
        ];
    }
}
