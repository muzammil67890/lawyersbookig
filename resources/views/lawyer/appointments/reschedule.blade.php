@extends('layouts.minimal')

@section('title', 'Reschedule '.$appointment->reference.' | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Reschedule appointment</h1>
            <p>{{ $appointment->reference }} with {{ $appointment->customer->name }}</p>
        </div>
        <a class="btn outline" href="{{ route('lawyer.appointments.show', $appointment) }}"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <div class="panel" style="padding:26px;max-width:620px;">
        <p class="subtext" style="margin-bottom:18px;">
            Moving this appointment to a new time keeps it confirmed &mdash; the customer will be notified.
        </p>
        @include('appointments._reschedule-fields', [
            'submitUrl' => route('lawyer.appointments.reschedule', $appointment),
            'slotsUrl' => route('lawyer.appointments.reschedule.slots', $appointment),
        ])
    </div>
</div>
@endsection
