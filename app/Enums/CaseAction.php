<?php

namespace App\Enums;

/**
 * Phase 7: the only ways a case's status can change after it is
 * created. Modeled directly on App\Enums\AppointmentAction, which the
 * same product already trusts for appointment lifecycle integrity --
 * each case here is a business action a user may request; the status
 * it produces, who may request it, and which statuses it may start
 * from are all defined here and nowhere else.
 *
 * Nothing in the application ever sets `cases.status` from request
 * input: a controller maps a route to one of these actions and
 * LegalCaseStatusService decides whether that action is valid right
 * now for the acting user.
 *
 * | Action        | From         | To          | Who                    |
 * |---------------|--------------|-------------|------------------------|
 * | activate      | pending      | active      | assigned lawyer        |
 * | start_progress| active       | in_progress | assigned lawyer        |
 * | complete      | in_progress  | completed   | assigned lawyer        |
 * | close         | completed    | closed      | assigned lawyer, admin |
 * | cancel        | pending      | cancelled   | customer               |
 * | cancel        | pending,active| cancelled  | assigned lawyer        |
 * | cancel        | pending,active,in_progress | cancelled | admin    |
 *
 * Reassigning a case to a different lawyer is a separate concept (see
 * LegalCasePolicy::assignLawyer() and LegalCaseStatusService::reassignLawyer())
 * -- it does not change `status` and is not one of these actions.
 */
enum CaseAction: string
{
    case ACTIVATE = 'activate';
    case START_PROGRESS = 'start_progress';
    case COMPLETE = 'complete';
    case CLOSE = 'close';
    case CANCEL = 'cancel';

    public function targetStatus(): CaseStatus
    {
        return match ($this) {
            self::ACTIVATE => CaseStatus::ACTIVE,
            self::START_PROGRESS => CaseStatus::IN_PROGRESS,
            self::COMPLETE => CaseStatus::COMPLETED,
            self::CLOSE => CaseStatus::CLOSED,
            self::CANCEL => CaseStatus::CANCELLED,
        };
    }

    /** @return list<UserRole> */
    public function allowedRoles(): array
    {
        return match ($this) {
            self::ACTIVATE, self::START_PROGRESS, self::COMPLETE => [UserRole::LAWYER],
            self::CLOSE => [UserRole::LAWYER, UserRole::ADMIN],
            self::CANCEL => [UserRole::CUSTOMER, UserRole::LAWYER, UserRole::ADMIN],
        };
    }

    /**
     * The statuses this action may start from when performed by the
     * given role. Cancellation reach depends on who is asking: a
     * customer may only withdraw a request that no lawyer has engaged
     * with yet, the assigned lawyer may step away up until active
     * work is complete, and an admin has the widest reach for
     * oversight/dispute resolution -- but never over a case that has
     * already reached a terminal status.
     *
     * @return list<CaseStatus>
     */
    public function allowedFrom(UserRole $role): array
    {
        return match ($this) {
            self::ACTIVATE => [CaseStatus::PENDING],
            self::START_PROGRESS => [CaseStatus::ACTIVE],
            self::COMPLETE => [CaseStatus::IN_PROGRESS],
            self::CLOSE => [CaseStatus::COMPLETED],
            self::CANCEL => match ($role) {
                UserRole::CUSTOMER => [CaseStatus::PENDING],
                UserRole::LAWYER => [CaseStatus::PENDING, CaseStatus::ACTIVE],
                UserRole::ADMIN => [CaseStatus::PENDING, CaseStatus::ACTIVE, CaseStatus::IN_PROGRESS],
            },
        };
    }

    /** Past-tense phrase used in user-facing "can no longer be ..." errors. */
    public function verb(): string
    {
        return match ($this) {
            self::ACTIVATE => 'activated',
            self::START_PROGRESS => 'moved into progress',
            self::COMPLETE => 'marked as completed',
            self::CLOSE => 'closed',
            self::CANCEL => 'cancelled',
        };
    }
}
