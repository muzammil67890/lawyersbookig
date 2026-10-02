@extends('layouts.minimal')

@section('title', 'My Reviews | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Reviews &amp; reputation</h1>
            <p>What clients are saying after a completed appointment with you.</p>
        </div>
        <a class="btn outline" href="{{ route('lawyer.dashboard') }}"><i class="fa-solid fa-arrow-left"></i> Back to dashboard</a>
    </div>

    @if (! $profile)
        <div class="panel" style="padding:22px;">
            <p class="subtext">Complete your lawyer onboarding to start receiving reviews.</p>
        </div>
    @else
        <div class="panel" style="padding:22px;margin-bottom:20px;">
            <div class="rating-summary">
                @if ($reputation['count'] > 0)
                    <span class="rating"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ number_format($reputation['average'], 1) }} / 5</span>
                    <span>based on {{ $reputation['count'] }} {{ Str::plural('review', $reputation['count']) }}</span>
                @else
                    <span class="subtext">No published reviews yet.</span>
                @endif
            </div>

            @if ($reputation['count'] > 0)
                <div class="rating-breakdown">
                    @for ($star = 5; $star >= 1; $star--)
                        @php $starCount = $reputation['breakdown'][$star] ?? 0; @endphp
                        <div class="rating-breakdown-row">
                            <span>{{ $star }} star</span>
                            <div class="rating-breakdown-bar"><span style="width:{{ $reputation['count'] > 0 ? round(($starCount / $reputation['count']) * 100) : 0 }}%"></span></div>
                            <span>{{ $starCount }}</span>
                        </div>
                    @endfor
                </div>
            @endif
        </div>

        <div class="panel" style="padding:22px;">
            <h3 style="margin-bottom:8px;">Recent reviews</h3>
            @if ($reviews->isEmpty())
                <p class="subtext">Reviews will appear here once a client reviews a completed appointment.</p>
            @else
                @foreach ($reviews as $review)
                    <div class="review-item">
                        <span class="rating"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ $review->rating }}/5</span>
                        @if ($review->comment)
                            <p>{{ $review->comment }}</p>
                        @endif
                        <span class="subtext">{{ $review->customer->name ?? 'A client' }} &middot; {{ $review->created_at->format('M j, Y') }}</span>
                    </div>
                @endforeach
                <div style="margin-top:14px;">{{ $reviews->links() }}</div>
            @endif
        </div>
    @endif
</div>
@endsection
