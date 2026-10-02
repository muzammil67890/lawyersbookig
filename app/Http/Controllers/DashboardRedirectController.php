<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single, centralized "where does this user belong" destination used
 * after login, after registration, and by the header's Dashboard link.
 * Keeping this in one place is what "role redirects should be clean
 * and centralized" (Phase 3 spec) means in practice -- no controller
 * anywhere else re-implements this branch.
 */
class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return match ($user->role) {
            UserRole::ADMIN => redirect()->route('admin.dashboard'),
            UserRole::LAWYER => redirect()->route('lawyer.dashboard'),
            UserRole::CUSTOMER => redirect()->route('customer.dashboard'),
        };
    }
}
