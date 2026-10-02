@extends('layouts.minimal')

@section('title', 'Leave a Review | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Leave a review</h1>
            <p>For your appointment {{ $appointment->reference }} with {{ $appointment->lawyerProfile->display_name }}.</p>
        </div>
        <a class="btn outline" href="{{ route('customer.appointments.show', $appointment) }}"><i class="fa-solid fa-arrow-left"></i> Back to appointment</a>
    </div>

    @if ($errors->any())
        <div class="demo-note" style="margin-bottom:18px;border-color:var(--red);color:var(--red);">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="panel" style="padding:22px;max-width:560px;">
        <form method="POST" action="{{ route('customer.appointments.review.store', $appointment) }}">
            @csrf

            @include('customer.reviews._form', ['review' => null])

            <button class="btn gold" type="submit"><i class="fa-solid fa-star"></i> Publish review</button>
        </form>
    </div>
</div>
@endsection
