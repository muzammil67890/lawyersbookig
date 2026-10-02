@extends('layouts.minimal')

@section('title', 'Reset Password | Legal Connect')

@section('content')
<div class="auth-page">
    <div class="auth-side">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><i class="fa-solid fa-scale-balanced"></i></span>LEGAL CONNECT</a>
        <h1>Choose a new password.</h1>
        <p>Your reset link is valid for a limited time and can only be used once.</p>
    </div>
    <div class="auth-form-wrap">
        <h2>Reset password</h2>
        <p>Enter a new password for your account.</p>

        <form method="POST" action="{{ route('password.store') }}" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field {{ $errors->has('email') ? 'invalid' : '' }}">
                <label for="resetEmail">Email address</label>
                <input id="resetEmail" name="email" value="{{ old('email', $email) }}" placeholder="you@example.com" required type="email" autofocus>
                <div class="error">{{ $errors->first('email') }}</div>
            </div>
            <div class="two-field">
                <div class="field {{ $errors->has('password') ? 'invalid' : '' }}">
                    <label for="resetPassword">New password</label>
                    <input id="resetPassword" name="password" minlength="8" placeholder="At least 8 characters" required type="password">
                    <div class="error">{{ $errors->first('password') }}</div>
                </div>
                <div class="field">
                    <label for="resetConfirm">Confirm password</label>
                    <input id="resetConfirm" name="password_confirmation" placeholder="Repeat password" required type="password">
                    <div class="error">Passwords do not match.</div>
                </div>
            </div>
            <button class="btn gold full" type="submit">Reset password <i class="fa-solid fa-arrow-right"></i></button>
        </form>
        <p class="auth-switch"><a href="{{ route('login') }}">Back to sign in</a></p>
    </div>
</div>
@endsection
