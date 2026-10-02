<?php

namespace App\Http\Controllers\Lawyer;

use App\Http\Controllers\Controller;
use App\Services\MessagingService;
use App\Services\NotificationCenterService;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Protected lawyer landing page. Shows onboarding/verification status
 * (Phase 4), the unread-message badge via MessagingService (Phase 8),
 * and, since Phase 9, a reputation summary via ReviewService -- the
 * same aggregation the public profile and the lawyer's own reviews
 * list use, so the dashboard figure can never disagree with either.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly MessagingService $messaging,
        private readonly ReviewService $reviews,
        private readonly NotificationCenterService $notifications,
    ) {
    }

    public function index(Request $request): View
    {
        $profile = $request->user()->lawyerProfile()->with('verification')->first();

        return view('dashboard.lawyer', [
            'user' => $request->user(),
            'profile' => $profile,
            'recentNotifications' => $this->notifications->recentFor($request->user()),
            'unreadMessages' => $this->messaging->totalUnreadCount($request->user()),
            'reputation' => $profile ? $this->reviews->reputation($profile) : null,
        ]);
    }
}
