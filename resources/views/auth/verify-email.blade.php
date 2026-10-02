@extends('layouts.minimal')

@section('title', 'Verify Email | Legal Connect')

@section('content')
<div class="auth-page">
    <div class="auth-side">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><i class="fa-solid fa-scale-balanced"></i></span>LEGAL CONNECT</a>
        <h1>One more step.</h1>
        <p>Confirm your email address to unlock your Legal Connect workspace.</p>
    </div>
    <div class="auth-form-wrap">
        <h2>Verify your email</h2>
        <p>Thanks for signing up! Before getting started, please verify your email address by clicking the link we emailed to you.</p>

        @if (session('status') === 'verification-link-sent')
            <div class="demo-note" style="border:1px solid var(--line);border-radius:8px;padding:12px;margin-bottom:16px;">
                A new verification link has been sent to the email address you provided during registration.
            </div>
        @endif

        <div class="demo-note" style="margin-top:0;padding-top:0;border-top:0;">
            Didn't get the email? In local development, mail is not actually delivered unless SMTP/Mailpit is configured
            &mdash; check <code>storage/logs/laravel.log</code> for the link, or use the resend button below.
        </div>

        <form method="POST" action="{{ route('verification.send') }}" style="margin-top:18px;">
            @csrf
            <button class="btn gold full" type="submit">Resend verification email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" style="margin-top:12px;">
            @csrf
            <button class="btn outline full" type="submit">Log out</button>
        </form>
    </div>
</div>
@endsection
