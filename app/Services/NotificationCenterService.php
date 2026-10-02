<?php

namespace App\Services;

use App\Models\User;
use App\Support\NotificationPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Phase 10: the single place the Notification Center reads and changes
 * notification state. It reuses Laravel's own `notifications` table and
 * the `Notifiable` relations already on User -- no second notification
 * system exists.
 *
 * Every method takes the already-authenticated User and goes through
 * `$user->notifications()`, so every query is constrained to
 * `notifiable_type = User AND notifiable_id = $user->id`. No method
 * accepts a user id, so ownership can never come from request input.
 */
class NotificationCenterService
{
    public const FILTER_ALL = 'all';
    public const FILTER_UNREAD = 'unread';
    public const FILTER_READ = 'read';

    public const FILTERS = [self::FILTER_ALL, self::FILTER_UNREAD, self::FILTER_READ];

    public function __construct(private readonly NotificationPresenter $presenter)
    {
    }

    public function normalizeFilter(mixed $filter): string
    {
        return is_string($filter) && in_array($filter, self::FILTERS, true) ? $filter : self::FILTER_ALL;
    }

    public function paginateFor(User $user, string $filter, int $perPage = 15): LengthAwarePaginator
    {
        $query = $user->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id');

        if ($filter === self::FILTER_UNREAD) {
            $query->whereNull('read_at');
        } elseif ($filter === self::FILTER_READ) {
            $query->whereNotNull('read_at');
        }

        $page = $query->paginate($perPage)->withQueryString();
        $page->through(fn (DatabaseNotification $n) => $this->presenter->present($n, $user));

        return $page;
    }

    /** @return Collection<int, array> */
    public function recentFor(User $user, int $limit = 5): Collection
    {
        return $user->notifications()->reorder()
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit($limit)->get()
            ->map(fn (DatabaseNotification $n) => $this->presenter->present($n, $user));
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /** Resolves a notification only if it belongs to $user; anything else is a 404. */
    public function findOwned(User $user, string $id): DatabaseNotification
    {
        return $user->notifications()->whereKey($id)->firstOrFail();
    }

    public function markRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markUnread(DatabaseNotification $notification): void
    {
        $notification->markAsUnread();
    }

    /** Marks only $user's own unread notifications; returns how many changed. */
    public function markAllRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
