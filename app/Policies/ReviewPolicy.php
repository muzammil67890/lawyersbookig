<?php

namespace App\Policies;

use App\Enums\AppointmentStatus;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\Review;
use App\Models\User;

/**
 * Phase 9: server-side authorization for review records, modeled
 * directly on App\Policies\AppointmentPolicy and LegalCasePolicy.
 *
 * A review's identity is entirely derived from its appointment: the
 * reviewer is the appointment's customer and the reviewed party is the
 * appointment's lawyer. Nothing here ever trusts a customer_id or
 * lawyer_profile_id supplied by the request -- see
 * ReviewController::store() and StoreReviewRequest, which never accept
 * those as input fields at all.
 *
 * This policy answers "is this user allowed to perform this kind of
 * action on this record/appointment at all" (role + active account +
 * ownership + appointment state). It is the single place that decides
 * review eligibility, reused unchanged by the controller, the service,
 * and the Blade views ("Leave a review" button visibility) -- exactly
 * the requirement #8 shape ("do not rely only on hiding a button").
 */
class ReviewPolicy
{
    /**
     * Can this user leave a review for this appointment?
     *
     * Every one of the business rules from the phase brief lives here,
     * in one place, so the controller, ReviewService, and the Blade
     * "Leave a review" button can never disagree about eligibility:
     *
     *  1. the acting account is an active customer;
     *  2. the appointment belongs to that customer (never another
     *     customer's appointment -- closes the IDOR/spoofing angle);
     *  3. the appointment has a lawyer at all (defensive -- every
     *     appointment has one, but a null would otherwise NPE below);
     *  4. the appointment's lawyer is not the acting user themselves
     *     (structurally impossible today since only customers can own
     *     an appointment, but kept as an explicit belt-and-braces
     *     guard against a lawyer ever reviewing themselves);
     *  5. the appointment has actually reached COMPLETED -- no other
     *     status, however far along, ever qualifies;
     *  6. the appointment does not already have a review (duplicate
     *     protection at the authorization layer, on top of the
     *     database unique constraint and the service-layer lock).
     */
    public function create(User $user, Appointment $appointment): bool
    {
        if ($user->status !== UserStatus::ACTIVE || ! $user->isCustomer()) {
            return false;
        }

        if ($user->id !== $appointment->customer_id) {
            return false;
        }

        if ($appointment->lawyer_profile_id === null) {
            return false;
        }

        if ($appointment->lawyerProfile?->user_id === $user->id) {
            return false;
        }

        if ($appointment->status !== AppointmentStatus::COMPLETED) {
            return false;
        }

        // Uses the loaded relation when the caller already eager-loaded
        // it (the common case, e.g. Blade's @can on an already-fetched
        // appointment); falls back to an exists() query otherwise so a
        // fresh, unhydrated model is still answered correctly.
        return ! ($appointment->relationLoaded('review')
            ? $appointment->review !== null
            : $appointment->review()->exists());
    }

    /** A review is visible to the customer who wrote it, the reviewed lawyer, and admins. */
    public function view(User $user, Review $review): bool
    {
        if ($user->status !== UserStatus::ACTIVE) {
            return false;
        }

        if ($user->isCustomer()) {
            return $user->id === $review->customer_id;
        }

        if ($user->isLawyer()) {
            return $user->lawyerProfile !== null
                && $user->lawyerProfile->id === $review->lawyer_profile_id;
        }

        return $user->isAdmin();
    }

    /** Only the review's own author may edit its rating/comment -- never the lawyer, never an admin. */
    public function update(User $user, Review $review): bool
    {
        return $user->status === UserStatus::ACTIVE
            && $user->isCustomer()
            && $user->id === $review->customer_id;
    }

    /** Only the review's own author may withdraw it entirely. */
    public function delete(User $user, Review $review): bool
    {
        return $user->status === UserStatus::ACTIVE
            && $user->isCustomer()
            && $user->id === $review->customer_id;
    }

    /**
     * Deliberately narrow admin oversight (matching LegalCasePolicy's
     * approach to admin abilities): an admin may move a review between
     * published and rejected to police abusive/spam content, but may
     * never edit its rating/comment and may never hard-delete it --
     * the moderation trail (who wrote what) is preserved.
     */
    public function moderate(User $user, Review $review): bool
    {
        return $user->status === UserStatus::ACTIVE && $user->isAdmin();
    }

    /** Admin-only: list/inspect every review on the platform for oversight. */
    public function viewAny(User $user): bool
    {
        return $user->status === UserStatus::ACTIVE && $user->isAdmin();
    }
}
