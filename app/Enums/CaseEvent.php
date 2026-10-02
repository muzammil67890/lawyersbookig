<?php

namespace App\Enums;

/** What happened to a case, as told to the other party in a notification. Mirrors AppointmentEvent. */
enum CaseEvent: string
{
    case ASSIGNED = 'assigned';
    case REASSIGNED = 'reassigned';
    case ACTIVATED = 'activated';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
    case DOCUMENT_UPLOADED = 'document_uploaded';

    public function message(): string
    {
        return match ($this) {
            self::ASSIGNED => 'A new case was assigned to you.',
            self::REASSIGNED => 'A case has been reassigned.',
            self::ACTIVATED => 'Your case is now active.',
            self::IN_PROGRESS => 'Your case is now in progress.',
            self::COMPLETED => 'Your case has been marked as completed.',
            self::CLOSED => 'Your case has been closed.',
            self::CANCELLED => 'A case was cancelled.',
            self::DOCUMENT_UPLOADED => 'A new document was uploaded to your case.',
        };
    }
}
