<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
    case RESCHEDULED = 'rescheduled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Statuses that still occupy the lawyer's calendar. A cancelled,
     * rejected or rescheduled (moved-away) appointment frees its slot;
     * a completed one keeps it, since that time was actually used.
     *
     * @return list<self>
     */
    public static function slotBlocking(): array
    {
        return [self::PENDING, self::CONFIRMED, self::COMPLETED];
    }

    /** Values of {@see slotBlocking()}, ready for a `whereIn` clause. */
    public static function slotBlockingValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::slotBlocking());
    }

    /** Statuses from which no further transition exists. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::REJECTED, self::CANCELLED, self::COMPLETED, self::RESCHEDULED], true);
    }
}
