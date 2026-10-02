<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\LawyerVerification;
use App\Services\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Protected admin landing page. Phase 4 adds a real pending-lawyer-
 * verification count linking into the review queue; the rest of the
 * admin panel (service management, reports, etc.) is still out of
 * scope and remains the Phase 3 placeholder.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly NotificationCenterService $notifications)
    {
    }

    public function index(Request $request): View
    {
        return view('dashboard.admin', [
            'user' => $request->user(),
            'recentNotifications' => $this->notifications->recentFor($request->user()),
            'pendingVerifications' => LawyerVerification::query()
                ->where('status', VerificationStatus::PENDING->value)
                ->count(),
        ]);
    }
}
