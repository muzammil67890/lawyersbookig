<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Phase 10: a notification is private to the account it was sent to.
 * Ownership is the notifiable morph (type AND id), so a notification for
 * some other model that happens to share a numeric id is never matched.
 * Admins get no special access: an admin can read only their own.
 */
class NotificationPolicy
{
    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $user->status === UserStatus::ACTIVE
            && $notification->notifiable_type === $user->getMorphClass()
            && (string) $notification->notifiable_id === (string) $user->getKey();
    }

    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $this->view($user, $notification);
    }
}
