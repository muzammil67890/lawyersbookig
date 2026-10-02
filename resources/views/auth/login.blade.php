@extends('layouts.minimal')

@section('title', 'Sign In | Legal Connect')

@section('content')
<div class="auth-page">
    <div class="auth-side">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><i class="fa-solid fa-scale-balanced"></i></span>LEGAL CONNECT</a>
        <h1>Your legal world, connected.</h1>
        <p>Sign in to manage appointments, messages and legal services from one professional workspace.</p>
        @if (app()->environment('local'))
            <div class="demo-note">
                <strong>Development accounts</strong><br>
                Customer: customer@legalconnect.test / password<br>
                Lawyer (approved): lawyer.approved@legalconnect.test / password<br>
                Admin: admin@legalconnect.test / password<br>
                These are throwaway local seed accounts, not real people.
            </div>
        @endif
    </div>
    <div class="auth-form-wrap">
        <h2>Welcome back</h2>
        <p>Sign in to your Legal Connect account.</p>

        @if (session('status'))
            <div class="demo-note" style="border:1px solid var(--line);border-radius:8px;padding:12px;margin-bottom:16px;">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" novalidate>
            @csrf
            <div class="field {{ $errors->has('email') ? 'invalid' : '' }}">
                <label for="loginEmail">Email address</label>
                <input id="loginEmail" name="email" value="{{ old('email') }}" placeholder="you@example.com" required type="email" autofocus>
                <div class="error">{{ $errors->first('email') }}</div>
            </div>
            <div class="field {{ $errors->has('password') ? 'invalid' : '' }}">
                <label for="loginPassword">Password</label>
                <input id="loginPassword" name="password" minlength="6" placeholder="Your password" required type="password">
                <div class="error">{{ $errors->first('password') }}</div>
            </div>
            <div class="form-inline">
                <label><input id="rememberLogin" name="remember" type="checkbox"> Remember me</label>
                <a href="{{ route('password.request') }}">Forgot password?</a>
            </div>
            <button class="btn gold full" type="submit">Sign in <i class="fa-solid fa-arrow-right"></i></button>
        </form>
        <p class="auth-switch">New to Legal Connect? <a href="{{ route('register') }}">Create an account</a></p>
    </div>
</div>
@endsection
