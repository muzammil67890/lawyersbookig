@extends('layouts.minimal')

@section('title', 'Create Account | Legal Connect')

@section('content')
<div class="auth-page">
    <div class="auth-side">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><i class="fa-solid fa-scale-balanced"></i></span>LEGAL CONNECT</a>
        <h1>A clearer path to legal support.</h1>
        <p>Create an account to save professionals, arrange consultations and organize your legal matters.</p>
        <div class="demo-note">
            <strong>Real account, still an early platform</strong><br>
            Your account is created in our database and protected by a real password. Appointments, cases, documents and messaging are still demo features and are not yet backed by this account.
        </div>
    </div>
    <div class="auth-form-wrap">
        <h2>Create your account</h2>
        <p>Start your Legal Connect experience.</p>

        <form method="POST" action="{{ route('register') }}" novalidate>
            @csrf
            <div class="field {{ $errors->has('name') ? 'invalid' : '' }}">
                <label for="regName">Full name</label>
                <input id="regName" name="name" value="{{ old('name') }}" placeholder="Your full name" required>
                <div class="error">{{ $errors->first('name') }}</div>
            </div>
            <div class="field {{ $errors->has('email') ? 'invalid' : '' }}">
                <label for="regEmail">Email address</label>
                <input id="regEmail" name="email" value="{{ old('email') }}" placeholder="you@example.com" required type="email">
                <div class="error">{{ $errors->first('email') }}</div>
            </div>
            <div class="field {{ $errors->has('phone') ? 'invalid' : '' }}">
                <label for="regPhone">Phone <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                <input id="regPhone" name="phone" value="{{ old('phone') }}" placeholder="+92 300 1234567">
                <div class="error">{{ $errors->first('phone') }}</div>
            </div>
            <div class="two-field">
                <div class="field {{ $errors->has('password') ? 'invalid' : '' }}">
                    <label for="regPassword">Password</label>
                    <input id="regPassword" name="password" minlength="8" placeholder="At least 8 characters" required type="password">
                    <div class="error">{{ $errors->first('password') }}</div>
                </div>
                <div class="field">
                    <label for="regConfirm">Confirm password</label>
                    <input id="regConfirm" name="password_confirmation" placeholder="Repeat password" required type="password">
                    <div class="error">Passwords do not match.</div>
                </div>
            </div>
            <div class="field {{ $errors->has('role') ? 'invalid' : '' }}">
                <label for="regRole">Account type</label>
                <select id="regRole" name="role">
                    <option value="customer" {{ old('role', 'customer') === 'customer' ? 'selected' : '' }}>Customer</option>
                    <option value="lawyer" {{ old('role') === 'lawyer' ? 'selected' : '' }}>Lawyer</option>
                </select>
                <div class="error">{{ $errors->first('role') }}</div>
                <p class="subtext" style="font-size:12px;margin-top:6px;">
                    Registering as a lawyer creates your account first. After signing in, you'll submit your
                    professional details along with a CNIC and face photo for admin review before your profile
                    becomes public.
                </p>
            </div>
            <button class="btn gold full" type="submit">Create account <i class="fa-solid fa-arrow-right"></i></button>
        </form>
        <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </div>
</div>
@endsection
