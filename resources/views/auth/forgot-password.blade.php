@extends('layouts.minimal')

@section('title', 'Forgot Password | Legal Connect')

@section('content')
<div class="auth-page">
    <div class="auth-side">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><i class="fa-solid fa-scale-balanced"></i></span>LEGAL CONNECT</a>
        <h1>Let's get you back in.</h1>
        <p>Tell us the email on your account and we will send a secure link to reset your password.</p>
        <div class="demo-note">
            <strong>Note</strong><br>
            For your privacy, we always show the same confirmation message here, whether or not that email belongs to an account.
        </div>
    </div>
    <div class="auth-form-wrap">
        <h2>Forgot your password?</h2>
        <p>Enter your email address and we'll send you a password reset link.</p>

        @if (session('status'))
            <div class="demo-note" style="border:1px solid var(--line);border-radius:8px;padding:12px;margin-bottom:16px;">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf
            <div class="field {{ $errors->has('email') ? 'invalid' : '' }}">
                <label for="forgotEmail">Email address</label>
                <input id="forgotEmail" name="email" value="{{ old('email') }}" placeholder="you@example.com" required type="email" autofocus>
                <div class="error">{{ $errors->first('email') }}</div>
            </div>
            <button class="btn gold full" type="submit">Email password reset link <i class="fa-solid fa-arrow-right"></i></button>
        </form>
        <p class="auth-switch">Remembered it after all? <a href="{{ route('login') }}">Sign in</a></p>
    </div>
</div>
@endsection
