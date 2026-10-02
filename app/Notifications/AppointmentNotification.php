<?php

namespace App\Notifications;

use App\Enums\AppointmentEvent;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the other party to an appointment that something happened to
 * it (booked, confirmed, rejected, cancelled, rescheduled). Database
 * channel only, like the existing lawyer-application notifications --
 * no mail infrastructure is required for local development.
 *
 * The payload is deliberately minimal and contains nothing private: an
 * event name, a fixed message, the appointment id and reference, and
 * its date/time. It never carries the customer's notes, the
 * cancellation reason, contact details, verification documents or any
 * admin-side data. The recipient's own dashboard resolves the id to a
 * link, and that page is itself authorization-protected.
 */
class AppointmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Appointment $appointment,
        private readonly AppointmentEvent $event,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_'.$this->event->value,
            'event' => $this->event->value,
            'message' => $this->event->message(),
            'appointment_id' => $this->appointment->id,
            'reference' => $this->appointment->reference,
            'appointment_date' => $this->appointment->appointment_date->toDateString(),
            'start_time' => substr((string) $this->appointment->start_time, 0, 5),
        ];
    }
}
