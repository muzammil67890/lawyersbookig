<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle real customer/lawyer self-registration.
     *
     * The only data that ever reaches User::create() is
     * RegisterRequest::validated() -- 'role' has already been
     * whitelisted to customer|lawyer there, so this controller never
     * has to (and never does) trust a raw request value for it. No
     * LawyerProfile/LawyerVerification row is created here even for a
     * lawyer registrant: that onboarding workflow belongs to Phase 4.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => UserStatus::ACTIVE->value,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('dashboard.redirect');
    }
}
