@extends('layouts.minimal')

@section('title', 'Review Lawyer Application | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>{{ $verification->user->name }}</h1>
            <p>{{ $verification->user->email }} &middot; Verification status: {{ $verification->status->label() }}</p>
        </div>
        <a class="btn outline" href="{{ route('admin.lawyer-verifications.index') }}"><i class="fa-solid fa-arrow-left"></i> Back to queue</a>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    @php $profile = $verification->user->lawyerProfile; @endphp

    <div class="panel" style="padding:22px;margin-bottom:18px;">
        <h3>Professional information</h3>
        @if ($profile)
            <div class="two-field" style="margin-top:12px;">
                <div>
                    <span class="subtext">Title</span><br>
                    <strong>{{ $profile->title ?? '&mdash;' }}</strong>
                </div>
                <div>
                    <span class="subtext">Years of experience</span><br>
                    <strong>{{ $profile->years_of_experience }}</strong>
                </div>
                <div>
                    <span class="subtext">City</span><br>
                    <strong>{{ $profile->city ?? '&mdash;' }}</strong>
                </div>
                <div>
                    <span class="subtext">Consultation fee</span><br>
                    <strong>Rs. {{ number_format((float) $profile->consultation_fee, 0) }}</strong>
                </div>
                <div>
                    <span class="subtext">Bar council</span><br>
                    <strong>{{ $profile->bar_council ?? '&mdash;' }}</strong>
                </div>
                <div>
                    <span class="subtext">License number</span><br>
                    <strong>{{ $profile->license_number ?? '&mdash;' }}</strong>
                </div>
            </div>
            <p style="margin-top:16px;"><strong>Bio</strong><br><span class="subtext">{{ $profile->bio }}</span></p>
            @if ($profile->education)
                <p><strong>Education</strong><br><span class="subtext">{{ $profile->education }}</span></p>
            @endif
            <p>
                <strong>Services</strong><br>
                <span class="tag-row">
                    @forelse ($profile->services as $service)
                        <span class="tag">{{ $service->name }}</span>
                    @empty
                        <span class="subtext">None selected.</span>
                    @endforelse
                </span>
            </p>
            @if ($profile->specializations->isNotEmpty())
                <p>
                    <strong>Specializations</strong><br>
                    <span class="tag-row">
                        @foreach ($profile->specializations as $specialization)
                            <span class="tag">{{ $specialization->name }}</span>
                        @endforeach
                    </span>
                </p>
            @endif
            @if ($profile->profile_image)
                <p style="margin-top:16px;"><strong>Profile photo</strong><br>
                    <img src="{{ asset('storage/'.$profile->profile_image) }}" alt="Profile photo" style="max-width:160px;border-radius:12px;margin-top:8px;">
                </p>
            @endif
        @else
            <p class="subtext">No profile record found for this application.</p>
        @endif
    </div>

    <div class="panel" style="padding:22px;margin-bottom:18px;">
        <h3>Verification documents</h3>
        <p class="subtext">These images are private and only visible to you and the lawyer. They are provided as documents for manual review only &mdash; no automated facial recognition is performed.</p>
        <div class="two-field" style="margin-top:12px;">
            <div>
                <span class="subtext">CNIC image</span><br>
                <img src="{{ route('admin.lawyer-verifications.document', [$verification, 'cnic']) }}" alt="CNIC document" style="max-width:100%;border-radius:12px;margin-top:8px;border:1px solid var(--line);">
            </div>
            <div>
                <span class="subtext">Face photo</span><br>
                <img src="{{ route('admin.lawyer-verifications.document', [$verification, 'face']) }}" alt="Face document" style="max-width:100%;border-radius:12px;margin-top:8px;border:1px solid var(--line);">
            </div>
        </div>
        <p class="subtext" style="margin-top:10px;">Submitted: {{ $verification->submitted_at?->format('M j, Y g:i A') ?? '&mdash;' }}</p>
        @if ($verification->reviewer)
            <p class="subtext">Last reviewed by {{ $verification->reviewer->name }} on {{ $verification->reviewed_at?->format('M j, Y g:i A') }}</p>
        @endif
        @if ($verification->status->value === 'rejected' && $verification->rejection_reason)
            <p class="subtext"><strong>Current rejection reason:</strong> {{ $verification->rejection_reason }}</p>
        @endif
    </div>

    <div class="panel" style="padding:22px;">
        <h3>Decision</h3>
        <div class="two-field" style="align-items:start;">
            <form method="POST" action="{{ route('admin.lawyer-verifications.approve', $verification) }}">
                @csrf
                <button class="btn gold full" type="submit"><i class="fa-solid fa-check"></i> Approve application</button>
            </form>

            <form method="POST" action="{{ route('admin.lawyer-verifications.reject', $verification) }}">
                @csrf
                <div class="field {{ $errors->has('rejection_reason') ? 'invalid' : '' }}">
                    <label for="rejection_reason">Rejection reason</label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="3" placeholder="Explain what needs to be corrected or resubmitted" required>{{ old('rejection_reason') }}</textarea>
                    <div class="error">{{ $errors->first('rejection_reason') }}</div>
                </div>
                <button class="btn outline full" type="submit"><i class="fa-solid fa-xmark"></i> Reject application</button>
            </form>
        </div>
    </div>
</div>
@endsection
