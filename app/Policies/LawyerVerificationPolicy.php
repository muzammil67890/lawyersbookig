<?php

namespace App\Policies;

use App\Models\LawyerVerification;
use App\Models\User;

/**
 * Server-side authorization for lawyer verification records and their
 * CNIC/face documents (Phase 4 requirement #9). This is the *only*
 * place that decides who may view a verification's sensitive
 * documents -- routes never expose a raw storage path, and no
 * controller re-implements this check inline.
 */
class LawyerVerificationPolicy
{
    /**
     * Only admins may browse the verification queue.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * A verification record (and its CNIC/face images) may be viewed
     * by an admin, or by the lawyer it belongs to -- nobody else. A
     * customer, or another lawyer, must never be able to view it.
     */
    public function view(User $user, LawyerVerification $verification): bool
    {
        return $user->isAdmin() || $user->id === $verification->user_id;
    }

    /**
     * Only an admin may approve/reject a verification. A lawyer can
     * never review their own (or anyone else's) application -- this
     * is also structurally impossible today since the admin routes
     * sit behind `role:admin` middleware, but the policy makes the
     * rule explicit and independently enforced.
     */
    public function review(User $user, LawyerVerification $verification): bool
    {
        return $user->isAdmin();
    }
}
