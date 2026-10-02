<?php

namespace App\Enums;

/**
 * Phase 10: the six groups the Notification Center shows. The category is
 * derived from the `type` key every existing notification class already
 * writes into its payload -- no new column, no new notification class.
 */
enum NotificationCategory: string
{
    case APPOINTMENT = 'appointment';
    case MESSAGE = 'message';
    case CASE = 'case';
    case DOCUMENT = 'document';
    case REVIEW = 'review';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::APPOINTMENT => 'Appointment',
            self::MESSAGE => 'Message',
            self::CASE => 'Case',
            self::DOCUMENT => 'Document',
            self::REVIEW => 'Review',
            self::SYSTEM => 'System',
        };
    }

    /** Font Awesome icon already loaded by every layout. */
    public function icon(): string
    {
        return match ($this) {
            self::APPOINTMENT => 'fa-solid fa-calendar-check',
            self::MESSAGE => 'fa-regular fa-comments',
            self::CASE => 'fa-solid fa-folder-open',
            self::DOCUMENT => 'fa-solid fa-file-lines',
            self::REVIEW => 'fa-solid fa-star',
            self::SYSTEM => 'fa-solid fa-circle-info',
        };
    }

    /** Maps a stored payload `type` to its category. Unknown types fall back to SYSTEM. */
    public static function fromType(?string $type): self
    {
        $type = (string) $type;

        return match (true) {
            $type === 'case_document_uploaded' => self::DOCUMENT,
            str_starts_with($type, 'appointment_') => self::APPOINTMENT,
            str_starts_with($type, 'case_') => self::CASE,
            $type === 'new_message' => self::MESSAGE,
            $type === 'review_submitted' => self::REVIEW,
            default => self::SYSTEM,
        };
    }
}
