<?php

namespace App\Policies;

use App\Enums\AppointmentAction;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\User;

/**
 * Server-side authorization for appointment records (Phase 6 Part 1
 * requirements #24/#28, extended in Part 2). An appointment is private
 * to the two parties involved: the customer who booked it and the
 * lawyer it was booked with. Nobody else -- not another customer, not
 * another lawyer, and deliberately not an admin -- may view or act on
 * it. Admin appointment oversight is not part of the product yet and
 * can be added deliberately later.
 *
 * This policy answers "is this user allowed to perform this kind of
 * action on this record at all" (role + active account + ownership).
 * "Is the appointment in a state where the action is valid right now"
 * is answered by AppointmentStatusService, which is the only code that
 * changes appointment status and which calls back into this policy so
 * ownership is defined in exactly one place.
 */
class AppointmentPolicy
{
    /**
     * A customer may create an appointment for themselves. Which
     * lawyer/service/date/time is valid is a separate, more detailed
     * question that StoreAppointmentRequest and
     * AppointmentAvailabilityService answer -- this only gates "is
     * this user even the right kind of account to book at all".
     */
    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->status !== UserStatus::ACTIVE) {
            return false;
        }

        if ($user->isCustomer()) {
            return $user->id === $appointment->customer_id;
        }

        if ($user->isLawyer()) {
            return $user->lawyerProfile !== null
                && $user->lawyerProfile->id === $appointment->lawyer_profile_id;
        }

        return false;
    }

    /** Generic "modify" ability: allowed to the two parties, for any action they may ever take. */
    public function update(User $user, Appointment $appointment): bool
    {
        return $this->view($user, $appointment);
    }

    public function confirm(User $user, Appointment $appointment): bool
    {
        return $this->mayPerform($user, $appointment, AppointmentAction::CONFIRM);
    }

    public function reject(User $user, Appointment $appointment): bool
    {
        return $this->mayPerform($user, $appointment, AppointmentAction::REJECT);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->mayPerform($user, $appointment, AppointmentAction::CANCEL);
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $this->mayPerform($user, $appointment, AppointmentAction::COMPLETE);
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        return $this->mayPerform($user, $appointment, AppointmentAction::RESCHEDULE);
    }

    private function mayPerform(User $user, Appointment $appointment, AppointmentAction $action): bool
    {
        return in_array($user->role, $action->allowedRoles(), true)
            && $this->view($user, $appointment);
    }
}
