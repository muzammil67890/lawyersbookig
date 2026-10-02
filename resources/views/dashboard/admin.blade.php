@extends('layouts.minimal')

@section('title', 'Admin Panel | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Platform administration</h1>
            <p>Signed in as {{ $user->name }} (role: {{ $user->role->label() }}, status: {{ $user->status->label() }}).</p>
        </div>
        <a class="btn outline" href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Back to Legal Connect</a>
    </div>
    @include('notifications._recent')

    <div class="panel" style="padding:22px;margin-bottom:18px;">
        <h3>Lawyer verification queue</h3>
        <p class="subtext">
            {{ $pendingVerifications }} {{ $pendingVerifications === 1 ? 'application' : 'applications' }} awaiting review.
        </p>
        <a class="btn gold" style="margin-top:12px;" href="{{ route('admin.lawyer-verifications.index') }}">
            <i class="fa-solid fa-id-card-clip"></i> Review lawyer applications
        </a>
    </div>

    <div class="panel" style="padding:22px;margin-bottom:18px;">
        <h3>Case oversight</h3>
        <p class="subtext">
            View all cases on the platform, reassign a case to a different lawyer, or step in on a disputed case.
        </p>
        <a class="btn outline" style="margin-top:12px;" href="{{ route('admin.cases.index') }}">
            <i class="fa-solid fa-folder-open"></i> Case oversight
        </a>
    </div>

    <div class="panel" style="padding:22px;margin-bottom:18px;">
        <h3>Review oversight</h3>
        <p class="subtext">
            Inspect published reviews and hide any abusive or spam content from public lawyer profiles.
        </p>
        <a class="btn outline" style="margin-top:12px;" href="{{ route('admin.reviews.index') }}">
            <i class="fa-solid fa-star"></i> Review oversight
        </a>
    </div>

    <div class="panel" style="padding:22px;">
        <h3>This is a placeholder</h3>
        <p class="subtext">
            Phase 3 proves the admin authentication/authorization boundary: only an account with
            <code>role = admin</code> can reach this page, and it is server-side enforced, not hidden by the
            frontend. The full admin panel (service management, reports, etc.) beyond lawyer verification review is
            out of scope for this phase.
        </p>
        <form method="POST" action="{{ route('logout') }}" style="margin-top:18px;">
            @csrf
            <button class="btn" type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</button>
        </form>
    </div>
</div>
@endsection
