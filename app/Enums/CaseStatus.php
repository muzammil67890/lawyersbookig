<?php

namespace App\Enums;

/**
 * Phase 7: extended with CANCELLED, the one genuinely missing status --
 * without it there was no way to end a case that never proceeds (a
 * customer withdraws before a lawyer engages, a lawyer cannot take it
 * on, or an admin halts a disputed case). The five original values are
 * unchanged so every Phase 6 row and the Phase 6 regression tests
 * keep working exactly as before.
 *
 * The full lifecycle, and who may move a case between which of these
 * values, is defined in App\Enums\CaseAction -- this enum only names
 * the states themselves.
 */
enum CaseStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'In Progress',
            default => ucfirst($this->value),
        };
    }

    /** Whether a case in this status may still be changed at all (documents, notes, lifecycle actions). */
    public function isTerminal(): bool
    {
        return in_array($this, [self::CLOSED, self::CANCELLED], true);
    }
}
