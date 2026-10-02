<?php

namespace App\Http\Controllers;

use App\Services\NotificationCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 10: the central Notification Center, shared by all three roles.
 * It lives under the plain auth+active middleware (like /dashboard) and
 * is not role-prefixed, because "my notifications" means the same thing
 * for everyone. All state changes are POST (CSRF-protected) and resolve
 * the notification through the authenticated user's own relation, so a
 * guessed id belonging to someone else is a 404, never a write.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationCenterService $center)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $this->center->normalizeFilter($request->query('filter'));

        return view('notifications.index', [
            'notifications' => $this->center->paginateFor($user, $filter),
            'filter' => $filter,
            'unreadCount' => $this->center->unreadCount($user),
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $record = $this->center->findOwned($request->user(), $notification);
        $this->authorize('update', $record);

        $this->center->markRead($record);

        return back();
    }

    public function markUnread(Request $request, string $notification): RedirectResponse
    {
        $record = $this->center->findOwned($request->user(), $notification);
        $this->authorize('update', $record);

        $this->center->markUnread($record);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->center->markAllRead($request->user());

        return back()->with('status', 'All notifications marked as read.');
    }
}
