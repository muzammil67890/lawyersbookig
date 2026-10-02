<?php

namespace App\Services;

use App\Enums\CaseAction;
use App\Enums\CaseEvent;
use App\Enums\CaseStatus;
use App\Models\LawyerProfile;
use App\Models\LegalCase;
use App\Models\User;
use App\Notifications\CaseNotification;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Phase 7: the only code in the application that changes a case's
 * status after creation, or reassigns it to a different lawyer.
 * Modeled directly on App\Services\AppointmentStatusService.
 *
 * Controllers never assign `status` from request input. They map a
 * route to one CaseAction (activate, start_progress, complete, close,
 * cancel) and call the matching method here, which:
 *
 *  1. authorizes the acting user through LegalCasePolicy (role, active
 *     account, ownership -- ownership is defined only there);
 *  2. opens a transaction, locks the case row, and re-reads it, so the
 *     state it validates is the committed state and not what the
 *     request saw a moment earlier;
 *  3. checks the current status against CaseAction's transition table
 *     for the acting role;
 *  4. applies the change atomically;
 *  5. only after the transaction commits, notifies the other party.
 */
class LegalCaseStatusService
{
    /**
     * Actions the given user could successfully request on this case
     * right now. Used by views to decide which buttons to render --
     * purely presentational; every action is re-validated server-side
     * when requested.
     *
     * @return list<CaseAction>
     */
    public function availableActions(LegalCase $case, User $actor): array
    {
        return array_values(array_filter(
            CaseAction::cases(),
            fn (CaseAction $action) => Gate::forUser($actor)->allows($action->value, $case)
                && $this->stateViolation($action, $case, $actor) === null,
        ));
    }

    /** Assigned lawyer accepts a pending case. */
    public function activate(LegalCase $case, User $actor): LegalCase
    {
        $activated = $this->transition($case, $actor, CaseAction::ACTIVATE, function (LegalCase $locked) {
            $locked->status = CaseStatus::ACTIVE;
            $locked->opened_at = $locked->opened_at ?? now()->toDateString();
            $locked->save();

            return $locked;
        });

        $this->notify(CaseEvent::ACTIVATED, $activated, $activated->customer);

        return $activated;
    }

    /** Assigned lawyer begins substantive work. */
    public function startProgress(LegalCase $case, User $actor): LegalCase
    {
        $updated = $this->transition($case, $actor, CaseAction::START_PROGRESS, function (LegalCase $locked) {
            $locked->status = CaseStatus::IN_PROGRESS;
            $locked->save();

            return $locked;
        });

        $this->notify(CaseEvent::IN_PROGRESS, $updated, $updated->customer);

        return $updated;
    }

    /** Assigned lawyer marks the substantive work complete. */
    public function complete(LegalCase $case, User $actor): LegalCase
    {
        $completed = $this->transition($case, $actor, CaseAction::COMPLETE, function (LegalCase $locked) {
            $locked->status = CaseStatus::COMPLETED;
            $locked->save();

            return $locked;
        });

        $this->notify(CaseEvent::COMPLETED, $completed, $completed->customer);

        return $completed;
    }

    /** Assigned lawyer or admin closes a completed case. */
    public function close(LegalCase $case, User $actor): LegalCase
    {
        $closed = $this->transition($case, $actor, CaseAction::CLOSE, function (LegalCase $locked) {
            $locked->status = CaseStatus::CLOSED;
            $locked->closed_at = now()->toDateString();
            $locked->save();

            return $locked;
        });

        $this->notify(CaseEvent::CLOSED, $closed, $closed->customer);

        return $closed;
    }

    /** Customer, assigned lawyer, or admin cancels the case, within the reach CaseAction::allowedFrom() grants their role. */
    public function cancel(LegalCase $case, User $actor, ?string $reason): LegalCase
    {
        $cancelled = $this->transition($case, $actor, CaseAction::CANCEL, function (LegalCase $locked) use ($actor, $reason) {
            $locked->status = CaseStatus::CANCELLED;
            $locked->closed_at = now()->toDateString();
            // Phase 11: previously appended into the shared `notes` column.
            // `notes` is now the lawyer's private notes, so a customer's
            // cancellation reason (or vice versa) must not land there --
            // it goes in its own column instead, visible to both parties
            // like an appointment's cancellation_reason already is.
            $locked->cancellation_reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
            $locked->save();

            return $locked;
        });

        $recipient = $actor->isCustomer()
            ? $cancelled->lawyerProfile?->user
            : $cancelled->customer;

        $this->notify(CaseEvent::CANCELLED, $cancelled, $recipient);

        return $cancelled;
    }

    /**
     * Admin-only reassignment of a case to a different lawyer.
     * Not a CaseAction/status transition -- it never touches `status`
     * -- so it is authorized separately via LegalCasePolicy::assignLawyer()
     * and blocked once a case has reached a terminal status, the same
     * boundary every other case mutation respects.
     */
    public function reassignLawyer(LegalCase $case, User $actor, LawyerProfile $lawyer): LegalCase
    {
        Gate::forUser($actor)->authorize('assignLawyer', $case);

        $updated = DB::transaction(function () use ($case, $lawyer) {
            $locked = LegalCase::query()
                ->with(['customer', 'lawyerProfile.user'])
                ->whereKey($case->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status->isTerminal()) {
                throw $this->error('A closed or cancelled case can no longer be reassigned.');
            }

            $previousLawyerUser = $locked->lawyerProfile?->user;

            $locked->lawyer_profile_id = $lawyer->id;
            $locked->save();

            return [$locked->fresh(['customer', 'lawyerProfile.user']), $previousLawyerUser];
        });

        [$fresh, $previousLawyerUser] = $updated;

        $this->notify(CaseEvent::REASSIGNED, $fresh, $lawyer->user);

        if ($previousLawyerUser && $previousLawyerUser->isNot($lawyer->user)) {
            $this->notify(CaseEvent::REASSIGNED, $fresh, $previousLawyerUser);
        }

        return $fresh;
    }

    /** Tells the assigned lawyer about a newly created (pending) case. */
    public function notifyAssigned(LegalCase $case): void
    {
        $this->notify(CaseEvent::ASSIGNED, $case, $case->lawyerProfile?->user);
    }

    /** Tells the other party a document was uploaded. */
    public function notifyDocumentUploaded(LegalCase $case, User $uploader): void
    {
        $recipient = $uploader->isCustomer() ? $case->lawyerProfile?->user : $case->customer;

        $this->notify(CaseEvent::DOCUMENT_UPLOADED, $case, $recipient);
    }

    /**
     * Runs one action inside a transaction, re-reading and locking the
     * row so the state validated is the committed state, not what the
     * request saw a moment earlier.
     *
     * @param  Closure(LegalCase): LegalCase  $apply  receives the freshly locked row
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws ValidationException
     */
    private function transition(LegalCase $case, User $actor, CaseAction $action, Closure $apply): LegalCase
    {
        Gate::forUser($actor)->authorize($action->value, $case);

        return DB::transaction(function () use ($case, $actor, $action, $apply) {
            $locked = LegalCase::query()
                ->with(['lawyerProfile.user', 'customer'])
                ->whereKey($case->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $violation = $this->stateViolation($action, $locked, $actor);

            if ($violation !== null) {
                throw $this->error($violation);
            }

            return $apply($locked);
        });
    }

    /** The reason the action cannot be applied to the case as it is now, or null if it can. */
    private function stateViolation(CaseAction $action, LegalCase $case, User $actor): ?string
    {
        if (! in_array($case->status, $action->allowedFrom($actor->role), true)) {
            return "This case can no longer be {$action->verb()}.";
        }

        return null;
    }

    /**
     * Notifications are sent only after the state change has
     * committed, and a failure to deliver one is reported but never
     * undoes or hides the change the user just made.
     */
    private function notify(CaseEvent $event, LegalCase $case, ?User $recipient): void
    {
        if ($recipient === null) {
            return;
        }

        try {
            $recipient->notify(new CaseNotification($case, $event));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['case' => $message]);
    }
}
