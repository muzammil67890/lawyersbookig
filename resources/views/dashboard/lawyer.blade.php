@extends('layouts.minimal')

@section('title', 'Lawyer Workspace | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Welcome back, {{ explode(' ', $user->name)[0] }}.</h1>
            <p>This is your real, authenticated lawyer account (role: {{ $user->role->label() }}, status: {{ $user->status->label() }}).</p>
        </div>
        <a class="btn outline" href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Back to Legal Connect</a>
    </div>
    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    @include('notifications._recent')

    @if (! $profile)
        <div class="panel" style="padding:22px;">
            <h3>Complete your lawyer application</h3>
            <p class="subtext">
                No lawyer profile exists for your account yet. To appear in Legal Connect and become bookable, submit
                your professional information along with a CNIC image and a face photo for admin review.
            </p>
            <a class="btn gold" style="margin-top:12px;" href="{{ route('lawyer.onboarding.create') }}">
                <i class="fa-solid fa-file-signature"></i> Start your application
            </a>
        </div>
    @else
        <div class="panel" style="padding:22px;margin-bottom:18px;">
            <h3>Verification status: {{ $profile->verification_status->label() }}</h3>

            @if ($profile->verification_status->value === 'pending')
                <p class="subtext">
                    Your application has been submitted and is awaiting review. You will not appear as a public or
                    bookable lawyer, and your face image has not been used for any automated verification &mdash; an
                    administrator manually reviews your CNIC and face documents.
                </p>
            @elseif ($profile->verification_status->value === 'approved')
                <p class="subtext">
                    Your application has been approved. Your profile is eligible for future public search and
                    booking features as they are released.
                </p>
            @elseif ($profile->verification_status->value === 'rejected')
                <p class="subtext">
                    Your application was rejected and you remain non-public and non-bookable.
                    @if ($profile->verification && $profile->verification->rejection_reason)
                        <br><strong>Reason:</strong> {{ $profile->verification->rejection_reason }}
                    @endif
                </p>
            @elseif ($profile->verification_status->value === 'suspended')
                <p class="subtext">
                    Your lawyer profile has been suspended by an administrator. Please contact support for details.
                </p>
            @endif

            <div class="two-field" style="margin-top:16px;">
                <div>
                    <strong>{{ $profile->title ?? $profile->display_name }}</strong><br>
                    <span class="subtext">{{ $profile->city }} &middot; {{ $profile->years_of_experience }} years experience</span>
                </div>
                <div>
                    <span class="subtext">Consultation fee</span><br>
                    <strong>Rs. {{ number_format((float) $profile->consultation_fee, 0) }}</strong>
                </div>
            </div>
        </div>
    @endif

    @if ($profile)
        <div class="panel" style="padding:22px;margin-bottom:18px;">
            <h3>Availability</h3>
            <p class="subtext">
                Configure the weekly hours (or one-off dates) customers can request an appointment with you.
            </p>
            <a class="btn outline" style="margin-top:12px;" href="{{ route('lawyer.availability.index') }}">
                <i class="fa-solid fa-calendar-days"></i> Manage availability
            </a>
        </div>

        <div class="panel" style="padding:22px;margin-bottom:18px;">
            <h3>Appointments</h3>
            <p class="subtext">
                Review appointment requests, confirm or decline them, mark completed visits, and reschedule or
                cancel where needed.
            </p>
            <a class="btn outline" style="margin-top:12px;" href="{{ route('lawyer.appointments.index') }}">
                <i class="fa-solid fa-calendar-check"></i> Manage appointments
            </a>
        </div>

        <div class="panel" style="padding:22px;margin-bottom:18px;">
            <h3>Cases</h3>
            <p class="subtext">
                Review cases assigned to you, accept new requests, track progress, and manage case documents.
            </p>
            <a class="btn outline" style="margin-top:12px;" href="{{ route('lawyer.cases.index') }}">
                <i class="fa-solid fa-folder-open"></i> Manage cases
            </a>
        </div>

        <div class="panel" style="padding:22px;margin-bottom:18px;">
            <h3>Messages
                @if ($unreadMessages > 0)
                    <span class="unread" style="display:inline-grid;vertical-align:middle;margin-left:6px;">{{ $unreadMessages }}</span>
                @endif
            </h3>
            <p class="subtext">
                Reply to customers who've reached out to you directly.
            </p>
            <a class="btn outline" style="margin-top:12px;" href="{{ route('lawyer.messages.index') }}">
                <i class="fa-regular fa-comments"></i> My messages
            </a>
        </div>
        <div class="panel" style="padding:22px;margin-bottom:18px;">
            <h3>Reviews &amp; reputation</h3>
            <p class="subtext">
                @if ($reputation && $reputation['count'] > 0)
                    <span class="rating"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ number_format($reputation['average'], 1) }} / 5</span>
                    from {{ $reputation['count'] }} {{ Str::plural('review', $reputation['count']) }}.
                @else
                    No published reviews yet.
                @endif
            </p>
            <a class="btn outline" style="margin-top:12px;" href="{{ route('lawyer.reviews.index') }}">
                <i class="fa-solid fa-star"></i> View reviews
            </a>
        </div>
    @endif

    <div class="panel" style="padding:22px;">
        <h3>Your account</h3>
        <form method="POST" action="{{ route('logout') }}" style="margin-top:18px;">
            @csrf
            <button class="btn" type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</button>
        </form>
    </div>
</div>
@endsection
