@extends('layouts.minimal')

@section('title', 'My Reviews | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>My reviews</h1>
            <p>Reviews you've left for lawyers you've consulted.</p>
        </div>
        <a class="btn outline" href="{{ route('customer.dashboard') }}"><i class="fa-solid fa-arrow-left"></i> Back to dashboard</a>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    <div class="panel" style="padding:0;">
        @if ($reviews->isEmpty())
            <div style="padding:22px;">
                <p class="subtext">
                    You haven't left any reviews yet. Once one of your appointments is marked completed, you'll be
                    able to leave a review from its appointment page.
                </p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Lawyer</th>
                            <th>Rating</th>
                            <th>Appointment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reviews as $review)
                            <tr>
                                <td>{{ $review->lawyerProfile->display_name ?? 'Lawyer no longer available' }}</td>
                                <td><span class="rating"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ $review->rating }}/5</span></td>
                                <td>{{ $review->appointment->reference ?? '—' }}</td>
                                <td><span class="status {{ $review->status->value }}">{{ ucfirst($review->status->value) }}</span></td>
                                <td>{{ $review->created_at->format('M j, Y') }}</td>
                                <td><a class="btn outline small" href="{{ route('customer.reviews.edit', $review) }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:18px 22px;">
                {{ $reviews->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
