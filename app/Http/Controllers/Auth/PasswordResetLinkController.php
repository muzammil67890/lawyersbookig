<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle a "forgot password" request.
     *
     * Laravel's password broker returns the SAME generic response
     * whether or not the address is registered
     * (Password::RESET_LINK_SENT vs Password::INVALID_USER both render
     * an identical "check your inbox" style message to the visitor),
     * so this endpoint never confirms or denies that an account exists
     * for the submitted email -- that check happens below.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT || $status === Password::INVALID_USER) {
            return back()->with('status', 'If an account exists for that email address, a password reset link has been sent.');
        }

        // Only a genuinely unexpected broker failure (e.g. mailer
        // misconfiguration) reaches here, and even then no information
        // about account existence is revealed.
        throw ValidationException::withMessages([
            'email' => 'We could not send a reset link right now. Please try again shortly.',
        ]);
    }
}
