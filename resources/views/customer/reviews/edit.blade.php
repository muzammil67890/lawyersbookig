@extends('layouts.minimal')

@section('title', 'Edit Your Review | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Edit your review</h1>
            <p>Your review of {{ $review->lawyerProfile->display_name }}{{ $review->appointment ? ' ('.$review->appointment->reference.')' : '' }}.</p>
        </div>
        <a class="btn outline" href="{{ route('customer.reviews.index') }}"><i class="fa-solid fa-arrow-left"></i> My reviews</a>
    </div>

    @if ($errors->any())
        <div class="demo-note" style="margin-bottom:18px;border-color:var(--red);color:var(--red);">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="panel" style="padding:22px;max-width:560px;margin-bottom:18px;">
        <form method="POST" action="{{ route('customer.reviews.update', $review) }}">
            @csrf
            @method('PUT')

            @include('customer.reviews._form', ['review' => $review])

            <button class="btn gold" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
        </form>
    </div>

    <div class="panel" style="padding:22px;max-width:560px;">
        <h3>Remove this review</h3>
        <p class="subtext">This permanently withdraws your review from {{ $review->lawyerProfile->display_name }}'s public profile.</p>
        <form method="POST" action="{{ route('customer.reviews.destroy', $review) }}" style="margin-top:12px;"
              onsubmit="return confirm('Remove this review? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <button class="btn danger small" type="submit"><i class="fa-solid fa-trash"></i> Remove review</button>
        </form>
    </div>
</div>
@endsection
