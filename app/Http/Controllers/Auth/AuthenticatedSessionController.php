<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Log a customer, lawyer or admin in.
     *
     * All credential checking, rate limiting and account-status
     * enforcement happens inside LoginRequest::authenticate() so it is
     * defined once and reused, not duplicated per role. The session ID
     * is regenerated immediately after a successful login to prevent
     * session fixation.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard.redirect'));
    }

    /**
     * Log the current user out.
     *
     * Only reachable via POST (see routes/auth.php) so logout can never
     * be triggered by a bare GET link, a prefetch, or a CSRF'd request.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
