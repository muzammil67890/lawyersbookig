<?php

namespace App\Enums;

/** What happened to an appointment, as told to the other party in a notification. */
enum AppointmentEvent: string
{
    case BOOKED = 'booked';
    case CONFIRMED = 'confirmed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case RESCHEDULED = 'rescheduled';

    public function message(): string
    {
        return match ($this) {
            self::BOOKED => 'A new appointment request was submitted.',
            self::CONFIRMED => 'Your appointment request was confirmed.',
            self::REJECTED => 'Your appointment request was declined.',
            self::CANCELLED => 'An appointment was cancelled.',
            self::RESCHEDULED => 'An appointment was moved to a new time.',
        };
    }
}
