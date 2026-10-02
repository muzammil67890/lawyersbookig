<?php

namespace App\Services;

use App\Enums\AppointmentAction;
use App\Enums\AppointmentEvent;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Phase 6 Part 2: the only code in the application that changes an
 * appointment's status after booking.
 *
 * Controllers never assign `status` from request input. They map a
 * route to one AppointmentAction (confirm, reject, cancel, complete,
 * reschedule) and call the matching method here, which:
 *
 *  1. authorizes the acting user through AppointmentPolicy (role,
 *     active account, ownership -- ownership is defined only there);
 *  2. opens a transaction, locks the appointment row, and re-reads it,
 *     so the state it validates is the committed state and not what
 *     the request saw a moment earlier;
 *  3. checks the current status against AppointmentAction's transition
 *     table and the time rules (e.g. cannot confirm an appointment that
 *     has already started);
 *  4. applies the change and any related writes atomically;
 *  5. only after the transaction commits, notifies the other party.
 *
 * Slot rules (availability, overlap) are not reimplemented here: they
 * are delegated to AppointmentAvailabilityService, the same code the
 * original booking uses.
 */
class AppointmentStatusService
{
    public function __construct(private readonly AppointmentAvailabilityService $availability)
    {
    }

    /**
     * Actions the given user could successfully request on this
     * appointment right now. Used by views to decide which buttons to
     * render -- purely presentational; every action is re-validated
     * server-side when requested.
     *
     * @return list<AppointmentAction>
     */
    public function availableActions(Appointment $appointment, User $actor): array
    {
        return array_values(array_filter(
            AppointmentAction::cases(),
            fn (AppointmentAction $action) => Gate::forUser($actor)->allows($action->value, $appointment)
                && $this->stateViolation($action, $appointment, $actor) === null,
        ));
    }

    /** Lawyer accepts a pending request. */
    public function confirm(Appointment $appointment, User $actor): Appointment
    {
        $confirmed = $this->transition($appointment, $actor, AppointmentAction::CONFIRM, function (Appointment $locked) {
            $lawyer = $locked->lawyerProfile;

            if (! $lawyer->isEligible()) {
                throw $this->error('Your lawyer profile is not currently eligible to confirm appointments.');
            }

            // Final check, inside the lock: the lawyer may have changed
            // their availability, or another appointment may have taken
            // the time, since the request was made.
            $this->availability->assertSlotAvailable(
                $lawyer,
                $locked->appointment_date,
                $locked->startsAt(),
                $locked->endsAt(),
                $locked->id,
                'appointment',
            );

            $locked->status = AppointmentStatus::CONFIRMED;
            $locked->save();

            return $locked;
        });

        $this->notify(AppointmentEvent::CONFIRMED, $confirmed, $confirmed->customer);

        return $confirmed;
    }

    /** Lawyer declines a pending request. */
    public function reject(Appointment $appointment, User $actor): Appointment
    {
        $rejected = $this->transition($appointment, $actor, AppointmentAction::REJECT, function (Appointment $locked) {
            $locked->status = AppointmentStatus::REJECTED;
            $locked->save();

            return $locked;
        });

        $this->notify(AppointmentEvent::REJECTED, $rejected, $rejected->customer);

        return $rejected;
    }

    /** Customer (pending or confirmed) or lawyer (confirmed) cancels. */
    public function cancel(Appointment $appointment, User $actor, ?string $reason): Appointment
    {
        $cancelled = $this->transition($appointment, $actor, AppointmentAction::CANCEL, function (Appointment $locked) use ($reason) {
            $locked->status = AppointmentStatus::CANCELLED;
            $locked->cancellation_reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
            $locked->save();

            return $locked;
        });

        $this->notify(AppointmentEvent::CANCELLED, $cancelled, $this->otherParty($cancelled, $actor));

        return $cancelled;
    }

    /** Lawyer marks a confirmed appointment whose start time has passed as completed. */
    public function complete(Appointment $appointment, User $actor): Appointment
    {
        return $this->transition($appointment, $actor, AppointmentAction::COMPLETE, function (Appointment $locked) {
            $locked->status = AppointmentStatus::COMPLETED;
            $locked->save();

            return $locked;
        });
    }

    /**
     * Moves an appointment to a new date/time.
     *
     * The existing row is never edited in place: it becomes
     * RESCHEDULED (releasing its slot) and a new appointment is
     * created for the new time with `rescheduled_from_id` pointing
     * back at it, so the history of the booking is preserved. A
     * customer-initiated move creates a PENDING appointment the lawyer
     * must confirm again; a lawyer-initiated move creates a CONFIRMED
     * one (the lawyer is the authority on their own calendar) and the
     * customer is notified and may cancel or move it again.
     *
     * Returns the NEW appointment.
     *
     * @throws ValidationException
     */
    public function reschedule(Appointment $appointment, User $actor, Carbon $date, string $startTime): Appointment
    {
        $new = $this->transition($appointment, $actor, AppointmentAction::RESCHEDULE, function (Appointment $old) use ($actor, $date, $startTime) {
            $lawyer = $old->lawyerProfile;

            if (! $lawyer->isEligible()) {
                throw $this->error('This lawyer is not currently available for booking.');
            }

            if ($old->service_id !== null && ! $lawyer->services()->active()->whereKey($old->service_id)->exists()) {
                throw $this->error('The service for this appointment is no longer offered.');
            }

            $start = Carbon::parse($date->toDateString().' '.$startTime);
            $end = $start->copy()->addMinutes($this->availability->durationMinutes());

            if ($start->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['start_time' => 'The selected time has already passed.']);
            }

            if ($date->toDateString() > now()->addDays($this->availability->bookingWindowDays())->toDateString()) {
                throw ValidationException::withMessages(['appointment_date' => 'The selected date is too far in the future.']);
            }

            if ($start->equalTo($old->startsAt())) {
                throw ValidationException::withMessages(['start_time' => 'That is already the time of this appointment.']);
            }

            $this->availability->assertSlotAvailable($lawyer, $date, $start, $end, $old->id);

            $old->status = AppointmentStatus::RESCHEDULED;
            $old->save();

            return Appointment::create([
                'reference' => $this->availability->generateReference(),
                'customer_id' => $old->customer_id,
                'lawyer_profile_id' => $old->lawyer_profile_id,
                'service_id' => $old->service_id,
                'appointment_date' => $date->toDateString(),
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'consultation_type' => $old->consultation_type,
                'status' => $actor->isLawyer() ? AppointmentStatus::CONFIRMED : AppointmentStatus::PENDING,
                'reason' => $old->reason,
                'rescheduled_from_id' => $old->id,
            ]);
        });

        $this->notify(AppointmentEvent::RESCHEDULED, $new, $this->otherParty($new, $actor));

        return $new;
    }

    /** Tells the lawyer about a newly booked (pending) request. */
    public function notifyBooked(Appointment $appointment): void
    {
        $this->notify(AppointmentEvent::BOOKED, $appointment, $appointment->lawyerProfile->user);
    }

    /**
     * Runs one action inside a transaction.
     *
     * The calendar lock (for actions that claim lawyer time) is taken
     * before the appointment row lock, and always in that order, so
     * two transactions can never wait on each other in a cycle.
     *
     * @param  Closure(Appointment): Appointment  $apply  receives the freshly locked row
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws ValidationException
     */
    private function transition(Appointment $appointment, User $actor, AppointmentAction $action, Closure $apply): Appointment
    {
        Gate::forUser($actor)->authorize($action->value, $appointment);

        return DB::transaction(function () use ($appointment, $actor, $action, $apply) {
            if ($action->claimsCalendarTime()) {
                $this->availability->lockLawyerCalendar($appointment->lawyer_profile_id);
            }

            $locked = Appointment::query()
                ->with(['lawyerProfile.user', 'customer'])
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $violation = $this->stateViolation($action, $locked, $actor);

            if ($violation !== null) {
                throw $this->error($violation);
            }

            return $apply($locked);
        });
    }

    /** The reason the action cannot be applied to the appointment as it is now, or null if it can. */
    private function stateViolation(AppointmentAction $action, Appointment $appointment, User $actor): ?string
    {
        if (! in_array($appointment->status, $action->allowedFrom($actor->role), true)) {
            return "This appointment can no longer be {$action->verb()}.";
        }

        if ($action->requiresFutureStart() && $appointment->hasStarted()) {
            return "This appointment has already started and can no longer be {$action->verb()}.";
        }

        if ($action->requiresStarted() && ! $appointment->hasStarted()) {
            return 'An appointment can only be marked as completed once its start time has passed.';
        }

        return null;
    }

    private function otherParty(Appointment $appointment, User $actor): User
    {
        return $actor->isLawyer() ? $appointment->customer : $appointment->lawyerProfile->user;
    }

    /**
     * Notifications are sent only after the state change has committed,
     * and a failure to deliver one is reported but never undoes or
     * hides the change the user just made.
     */
    private function notify(AppointmentEvent $event, Appointment $appointment, ?User $recipient): void
    {
        if ($recipient === null) {
            return;
        }

        try {
            $recipient->notify(new AppointmentNotification($appointment, $event));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['appointment' => $message]);
    }
}
