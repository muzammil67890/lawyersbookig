@extends('layouts.minimal')

@section('title', 'Customer Dashboard | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Welcome back, {{ explode(' ', $user->name)[0] }}.</h1>
            <p>This is your real, authenticated customer account (role: {{ $user->role->label() }}, status: {{ $user->status->label() }}).</p>
        </div>
        <a class="btn outline" href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Back to Legal Connect</a>
    </div>
    @include('notifications._recent')

    <div class="panel" style="padding:22px;margin-bottom:18px;">
        <h3>Appointments</h3>
        <p class="subtext">
            View your appointment requests, track their status, reschedule, or cancel where the lawyer's response
            still allows it.
        </p>
        <a class="btn outline" style="margin-top:12px;" href="{{ route('customer.appointments.index') }}">
            <i class="fa-solid fa-calendar-check"></i> My appointments
        </a>
    </div>

    <div class="panel" style="padding:22px;margin-bottom:18px;">
        <h3>Cases</h3>
        <p class="subtext">
            Open a case with a lawyer, track its status, share documents, and follow it through to closure.
        </p>
        <a class="btn outline" style="margin-top:12px;" href="{{ route('customer.cases.index') }}">
            <i class="fa-solid fa-folder-open"></i> My cases
        </a>
    </div>

    <div class="panel" style="padding:22px;margin-bottom:18px;">
        <h3>Messages
            @if ($unreadMessages > 0)
                <span class="unread" style="display:inline-grid;vertical-align:middle;margin-left:6px;">{{ $unreadMessages }}</span>
            @endif
        </h3>
        <p class="subtext">
            Message lawyers you're working with directly, and hear back the moment they reply.
        </p>
        <a class="btn outline" style="margin-top:12px;" href="{{ route('customer.messages.index') }}">
            <i class="fa-regular fa-comments"></i> My messages
        </a>
    </div>

    <div class="panel" style="padding:22px;">
        <h3>Your account</h3>
        <p class="subtext">
            Phase 3 built the real authentication and authorization system behind this page &mdash; login, logout,
            email verification, password reset, and server-side role/account-status checks.
        </p>
        <form method="POST" action="{{ route('logout') }}" style="margin-top:18px;">
            @csrf
            <button class="btn" type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</button>
        </form>
    </div>
</div>
@endsection
