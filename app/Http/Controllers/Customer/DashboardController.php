<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\MessagingService;
use App\Services\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Protected customer landing page. Phase 8 adds the unread-message
 * badge here (requirement #24) via MessagingService -- the same
 * single source of truth the messages inbox itself uses, so the
 * dashboard count and the inbox can never disagree.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly MessagingService $messaging,
        private readonly NotificationCenterService $notifications,
    )
    {
    }

    public function index(Request $request): View
    {
        return view('dashboard.customer', [
            'user' => $request->user(),
            'recentNotifications' => $this->notifications->recentFor($request->user()),
            'unreadMessages' => $this->messaging->totalUnreadCount($request->user()),
        ]);
    }
}
