<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     *
     * Type-hinting `EmailVerificationRequest` (rather than the base
     * `Request`) gets the signed-URL + matching-user-id checks for
     * free from the framework before this method ever runs; the
     * `signed` route middleware in routes/auth.php is the second,
     * route-level layer of that same protection.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard.redirect', ['verified' => 1]);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->route('dashboard.redirect', ['verified' => 1]);
    }
}
