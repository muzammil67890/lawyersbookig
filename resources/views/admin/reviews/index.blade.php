@extends('layouts.minimal')

@section('title', 'Reviews | Admin | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Reviews</h1>
            <p>Platform-wide review oversight. Rejecting a review hides it from the public profile without deleting it.</p>
        </div>
        <a class="btn outline" href="{{ route('admin.dashboard') }}"><i class="fa-solid fa-arrow-left"></i> Back to dashboard</a>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    <div class="panel" style="padding:16px 22px;margin-bottom:18px;display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn small {{ $status === null ? 'gold' : 'outline' }}" href="{{ route('admin.reviews.index') }}">All</a>
        @foreach (\App\Enums\ReviewStatus::cases() as $option)
            <a class="btn small {{ $status === $option ? 'gold' : 'outline' }}"
               href="{{ route('admin.reviews.index', ['status' => $option->value]) }}">{{ ucfirst($option->value) }}</a>
        @endforeach
    </div>

    <div class="panel" style="padding:0;">
        @if ($reviews->isEmpty())
            <div style="padding:22px;"><p class="subtext">No reviews match this filter.</p></div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Lawyer</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reviews as $review)
                            <tr>
                                <td>{{ $review->customer->name ?? 'Deleted user' }}</td>
                                <td>{{ $review->lawyerProfile->display_name ?? 'Deleted profile' }}</td>
                                <td><span class="rating"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ $review->rating }}/5</span></td>
                                <td><span class="status {{ $review->status->value }}">{{ ucfirst($review->status->value) }}</span></td>
                                <td>{{ $review->created_at->format('M j, Y') }}</td>
                                <td><a class="btn outline small" href="{{ route('admin.reviews.show', $review) }}">Inspect</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:18px 22px;">{{ $reviews->links() }}</div>
        @endif
    </div>
</div>
@endsection
