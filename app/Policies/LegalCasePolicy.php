<?php

namespace App\Policies;

use App\Enums\CaseAction;
use App\Enums\UserStatus;
use App\Models\LegalCase;
use App\Models\User;

/**
 * Phase 7: server-side authorization for case records, modeled
 * directly on App\Policies\AppointmentPolicy. A case is private to the
 * two parties involved -- the customer it belongs to and the lawyer
 * currently assigned to it -- plus an admin, who has deliberate,
 * narrow oversight (view, cancel, close, reassign) rather than
 * blanket mutation rights: an admin cannot activate, progress, or
 * complete a case, since that operational work belongs to the
 * assigned lawyer.
 *
 * This policy answers "is this user allowed to perform this kind of
 * action on this record at all" (role + active account + ownership).
 * "Is the case in a state where the action is valid right now" is
 * answered by LegalCaseStatusService, which calls back into this
 * policy so ownership is defined in exactly one place.
 */
class LegalCasePolicy
{
    /** A customer may create a case for themselves, for a lawyer they choose. */
    public function create(User $user): bool
    {
        return $user->status === UserStatus::ACTIVE && $user->isCustomer();
    }

    public function view(User $user, LegalCase $case): bool
    {
        if ($user->status !== UserStatus::ACTIVE) {
            return false;
        }

        if ($user->isCustomer()) {
            return $user->id === $case->customer_id;
        }

        if ($user->isLawyer()) {
            return $user->lawyerProfile !== null
                && $case->lawyer_profile_id !== null
                && $user->lawyerProfile->id === $case->lawyer_profile_id;
        }

        // Deliberate, narrow admin oversight (requirement #10):
        // an admin may look at any case, but every mutation ability
        // below is granted separately and much more narrowly.
        return $user->isAdmin();
    }

    /** Editing permitted case fields (title/description for the customer, notes for either party). */
    public function update(User $user, LegalCase $case): bool
    {
        return $this->view($user, $case) && ! $case->status->isTerminal();
    }

    public function viewDocuments(User $user, LegalCase $case): bool
    {
        return $this->view($user, $case);
    }

    /**
     * Deliberately excludes admin (requirement #10's "not every
     * mutation merely for having the role"): no admin route ever
     * calls this, and restricting it here too means an admin session
     * can't be used to upload on a party's behalf even if a route
     * were added carelessly later.
     */
    public function uploadDocuments(User $user, LegalCase $case): bool
    {
        return $this->view($user, $case) && ! $user->isAdmin() && ! $case->status->isTerminal();
    }

    /**
     * Admin-only reassignment to a different lawyer -- deliberately
     * separate from every other ability here, per requirement #10
     * ("admin should not automatically gain every mutation permission
     * merely because they have an ADMIN role"). Neither the customer
     * nor the current/new lawyer may reassign a case themselves.
     */
    public function assignLawyer(User $user, LegalCase $case): bool
    {
        return $user->status === UserStatus::ACTIVE && $user->isAdmin() && ! $case->status->isTerminal();
    }

    /**
     * Deletion is a separate, deliberately narrow admin-only ability
     * (requirement #10), and LegalCaseStatusService/CaseController
     * additionally refuse to delete anything but an already-cancelled
     * case with no remaining documents -- this policy only answers
     * "is this the right kind of account", not "is this case in a
     * deletable state".
     */
    public function delete(User $user, LegalCase $case): bool
    {
        return $user->status === UserStatus::ACTIVE && $user->isAdmin();
    }

    public function activate(User $user, LegalCase $case): bool
    {
        return $this->mayPerform($user, $case, CaseAction::ACTIVATE);
    }

    public function start_progress(User $user, LegalCase $case): bool
    {
        return $this->mayPerform($user, $case, CaseAction::START_PROGRESS);
    }

    public function complete(User $user, LegalCase $case): bool
    {
        return $this->mayPerform($user, $case, CaseAction::COMPLETE);
    }

    public function close(User $user, LegalCase $case): bool
    {
        return $this->mayPerform($user, $case, CaseAction::CLOSE);
    }

    public function cancel(User $user, LegalCase $case): bool
    {
        return $this->mayPerform($user, $case, CaseAction::CANCEL);
    }

    private function mayPerform(User $user, LegalCase $case, CaseAction $action): bool
    {
        return in_array($user->role, $action->allowedRoles(), true)
            && $this->view($user, $case);
    }
}
