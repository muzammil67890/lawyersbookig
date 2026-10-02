<?php

namespace App\Policies;

use App\Models\LawyerAvailability;
use App\Models\User;

/**
 * Server-side authorization for lawyer availability records (Phase 6
 * Part 1, requirement #8/#24). Ownership is derived exclusively from
 * the authenticated user's own `lawyerProfile` relationship -- never
 * from anything in the request (URL id, hidden field, form value) --
 * so a lawyer can only ever view/create/update/delete availability
 * rows that belong to their own profile.
 */
class LawyerAvailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLawyer() && $user->lawyerProfile !== null;
    }

    public function view(User $user, LawyerAvailability $availability): bool
    {
        return $user->isLawyer()
            && $user->lawyerProfile?->id === $availability->lawyer_profile_id;
    }

    public function create(User $user): bool
    {
        return $user->isLawyer() && $user->lawyerProfile !== null;
    }

    public function update(User $user, LawyerAvailability $availability): bool
    {
        return $this->view($user, $availability);
    }

    public function delete(User $user, LawyerAvailability $availability): bool
    {
        return $this->view($user, $availability);
    }
}
