@extends('layouts.minimal')

@section('title', 'Reschedule '.$appointment->reference.' | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Reschedule appointment</h1>
            <p>{{ $appointment->reference }} with {{ $appointment->lawyerProfile->display_name }}</p>
        </div>
        <a class="btn outline" href="{{ route('customer.appointments.show', $appointment) }}"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <div class="panel" style="padding:26px;max-width:620px;">
        <p class="subtext" style="margin-bottom:18px;">
            Moving this appointment will send a new request to the lawyer &mdash; it will need to be confirmed again.
        </p>
        @include('appointments._reschedule-fields', [
            'submitUrl' => route('customer.appointments.reschedule', $appointment),
            'slotsUrl' => route('customer.appointments.reschedule.slots', $appointment),
        ])
    </div>
</div>
@endsection
