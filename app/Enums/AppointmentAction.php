<?php

namespace App\Enums;

/**
 * The only ways an appointment's status can change after it has been
 * booked. Each case is a business action a user may request; the
 * status it produces, who may request it, and which statuses it may
 * start from are all defined here and nowhere else, so the transition
 * table documented in README.md has exactly one source of truth.
 *
 * Nothing in the application ever sets `appointments.status` from
 * request input: a controller maps a route to one of these actions and
 * AppointmentStatusService decides whether that action is valid.
 *
 * | Action     | From                 | To          | Who                          |
 * |------------|----------------------|-------------|------------------------------|
 * | confirm    | pending              | confirmed   | assigned lawyer              |
 * | reject     | pending              | rejected    | assigned lawyer              |
 * | cancel     | pending, confirmed   | cancelled   | customer                     |
 * | cancel     | confirmed            | cancelled   | assigned lawyer              |
 * | complete   | confirmed            | completed   | assigned lawyer (after start)|
 * | reschedule | pending, confirmed   | rescheduled | customer or assigned lawyer  |
 */
enum AppointmentAction: string
{
    case CONFIRM = 'confirm';
    case REJECT = 'reject';
    case CANCEL = 'cancel';
    case COMPLETE = 'complete';
    case RESCHEDULE = 'reschedule';

    public function targetStatus(): AppointmentStatus
    {
        return match ($this) {
            self::CONFIRM => AppointmentStatus::CONFIRMED,
            self::REJECT => AppointmentStatus::REJECTED,
            self::CANCEL => AppointmentStatus::CANCELLED,
            self::COMPLETE => AppointmentStatus::COMPLETED,
            self::RESCHEDULE => AppointmentStatus::RESCHEDULED,
        };
    }

    /** @return list<UserRole> */
    public function allowedRoles(): array
    {
        return match ($this) {
            self::CONFIRM, self::REJECT, self::COMPLETE => [UserRole::LAWYER],
            self::CANCEL, self::RESCHEDULE => [UserRole::CUSTOMER, UserRole::LAWYER],
        };
    }

    /**
     * The statuses this action may start from when performed by the
     * given role. A lawyer answers a pending request with confirm or
     * reject, so lawyer-side cancellation only applies once an
     * appointment has been confirmed.
     *
     * @return list<AppointmentStatus>
     */
    public function allowedFrom(UserRole $role): array
    {
        return match ($this) {
            self::CONFIRM, self::REJECT => [AppointmentStatus::PENDING],
            self::COMPLETE => [AppointmentStatus::CONFIRMED],
            self::RESCHEDULE => [AppointmentStatus::PENDING, AppointmentStatus::CONFIRMED],
            self::CANCEL => $role === UserRole::LAWYER
                ? [AppointmentStatus::CONFIRMED]
                : [AppointmentStatus::PENDING, AppointmentStatus::CONFIRMED],
        };
    }

    /** Whether the action claims time on the lawyer's calendar and so needs the calendar lock. */
    public function claimsCalendarTime(): bool
    {
        return in_array($this, [self::CONFIRM, self::RESCHEDULE], true);
    }

    /** Whether the appointment's start time must still be in the future. */
    public function requiresFutureStart(): bool
    {
        return in_array($this, [self::CONFIRM, self::CANCEL, self::RESCHEDULE], true);
    }

    /** Whether the appointment's start time must already have passed. */
    public function requiresStarted(): bool
    {
        return $this === self::COMPLETE;
    }

    /** Past-tense phrase used in user-facing "can no longer be ..." errors. */
    public function verb(): string
    {
        return match ($this) {
            self::CONFIRM => 'confirmed',
            self::REJECT => 'rejected',
            self::CANCEL => 'cancelled',
            self::COMPLETE => 'marked as completed',
            self::RESCHEDULE => 'rescheduled',
        };
    }
}
