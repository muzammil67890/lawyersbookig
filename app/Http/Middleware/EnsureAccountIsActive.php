<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks suspended/inactive accounts from reaching protected application
 * functionality, registered as the `active` middleware alias.
 *
 * This is deliberately separate from login-time status checking
 * (see LoginRequest::authenticate()) so that an account suspended *while*
 * the holder still has a live session is also cut off on their very next
 * request, rather than only at their next login.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== UserStatus::ACTIVE) {
            $status = $user->status->label();

            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => "Your account is {$status}. Please contact support."]);
        }

        return $next($request);
    }
}
