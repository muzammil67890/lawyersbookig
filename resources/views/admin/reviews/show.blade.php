@extends('layouts.minimal')

@section('title', 'Review Detail | Admin | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Review #{{ $review->id }}</h1>
            <p>Submitted {{ $review->created_at->format('M j, Y g:i A') }}.</p>
        </div>
        <a class="btn outline" href="{{ route('admin.reviews.index') }}"><i class="fa-solid fa-arrow-left"></i> All reviews</a>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    <div class="panel" style="padding:22px;max-width:640px;">
        <div class="rating-summary" style="margin-bottom:14px;">
            <span class="rating"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ $review->rating }}/5</span>
            <span class="status {{ $review->status->value }}">{{ ucfirst($review->status->value) }}</span>
        </div>

        @if ($review->comment)
            <p style="font-size:14px;line-height:1.7;margin-bottom:16px;">{{ $review->comment }}</p>
        @else
            <p class="subtext" style="margin-bottom:16px;">No written comment was left with this rating.</p>
        @endif

        <p style="font-size:13px;color:var(--muted);line-height:1.9;">
            <strong>Customer:</strong> {{ $review->customer->name ?? 'Deleted user' }} ({{ $review->customer->email ?? '—' }})<br>
            <strong>Lawyer:</strong> {{ $review->lawyerProfile->display_name ?? 'Deleted profile' }}<br>
            <strong>Appointment:</strong> {{ $review->appointment->reference ?? '—' }}
        </p>

        <div style="margin-top:20px;display:flex;gap:10px;">
            @if ($review->status->value !== 'rejected')
                <form method="POST" action="{{ route('admin.reviews.reject', $review) }}"
                      onsubmit="return confirm('Hide this review from the public profile?');">
                    @csrf
                    <button class="btn danger" type="submit"><i class="fa-solid fa-ban"></i> Reject &amp; hide</button>
                </form>
            @endif
            @if ($review->status->value !== 'published')
                <form method="POST" action="{{ route('admin.reviews.restore', $review) }}">
                    @csrf
                    <button class="btn gold" type="submit"><i class="fa-solid fa-check"></i> Restore to public</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
