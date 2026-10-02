@extends('layouts.minimal')

@section('title', 'Appointment '.$appointment->reference.' | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Appointment {{ $appointment->reference }}</h1>
            <p>Requested by {{ $appointment->customer->name }}</p>
        </div>
        <a class="btn outline" href="{{ route('lawyer.appointments.index') }}"><i class="fa-solid fa-arrow-left"></i> All appointments</a>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="demo-note" style="margin-bottom:18px;border-color:var(--red);color:var(--red);">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="panel" style="padding:22px;max-width:560px;">
        <div class="two-field">
            <div>
                <span class="subtext">Status</span><br>
                <span class="status {{ $appointment->status->value }}">{{ $appointment->status->label() }}</span>
            </div>
            <div>
                <span class="subtext">Consultation type</span><br>
                <strong>{{ $appointment->consultation_type->label() }}</strong>
            </div>
        </div>

        <div class="two-field" style="margin-top:16px;">
            <div>
                <span class="subtext">Date</span><br>
                <strong>{{ $appointment->appointment_date->format('l, F j, Y') }}</strong>
            </div>
            <div>
                <span class="subtext">Time</span><br>
                <strong>
                    {{ \Illuminate\Support\Carbon::parse($appointment->start_time)->format('g:i A') }}
                    @if ($appointment->end_time)
                        &ndash; {{ \Illuminate\Support\Carbon::parse($appointment->end_time)->format('g:i A') }}
                    @endif
                </strong>
            </div>
        </div>

        <div style="margin-top:16px;">
            <span class="subtext">Service</span><br>
            <strong>{{ $appointment->service->name ?? 'Not specified' }}</strong>
        </div>

        <div style="margin-top:16px;">
            <span class="subtext">Customer</span><br>
            <strong>{{ $appointment->customer->name }}</strong> &middot; {{ $appointment->customer->email }}
        </div>

        @if ($appointment->reason)
            <div style="margin-top:16px;">
                <span class="subtext">Notes from the customer</span>
                <p>{{ $appointment->reason }}</p>
            </div>
        @endif

        @if ($appointment->status->value === 'cancelled' && $appointment->cancellation_reason)
            <div style="margin-top:16px;">
                <span class="subtext">Cancellation reason</span>
                <p>{{ $appointment->cancellation_reason }}</p>
            </div>
        @endif

        @if ($appointment->rescheduledFrom)
            <div style="margin-top:16px;">
                <span class="subtext">Rescheduled from</span><br>
                <a href="{{ route('lawyer.appointments.show', $appointment->rescheduledFrom) }}">{{ $appointment->rescheduledFrom->reference }}</a>
            </div>
        @endif

        @if ($appointment->rescheduledTo)
            <div style="margin-top:16px;">
                <span class="subtext">Rescheduled to</span><br>
                <a href="{{ route('lawyer.appointments.show', $appointment->rescheduledTo) }}">{{ $appointment->rescheduledTo->reference }}</a>
            </div>
        @endif

        @php
            $actionValues = array_map(fn ($a) => $a->value, $actions);
        @endphp

        @if (count($actionValues))
            <div class="row-actions" style="margin-top:22px;gap:8px;flex-wrap:wrap;">
                @if (in_array('confirm', $actionValues, true))
                    <form method="POST" action="{{ route('lawyer.appointments.confirm', $appointment) }}">
                        @csrf
                        <button class="btn gold small" type="submit"><i class="fa-solid fa-check"></i> Confirm</button>
                    </form>
                @endif
                @if (in_array('reject', $actionValues, true))
                    <form method="POST" action="{{ route('lawyer.appointments.reject', $appointment) }}"
                          onsubmit="return confirm('Reject this appointment request?');">
                        @csrf
                        <button class="btn danger small" type="submit"><i class="fa-solid fa-xmark"></i> Reject</button>
                    </form>
                @endif
                @if (in_array('reschedule', $actionValues, true))
                    <a class="btn outline small" href="{{ route('lawyer.appointments.reschedule.form', $appointment) }}">
                        <i class="fa-solid fa-calendar-days"></i> Reschedule
                    </a>
                @endif
                @if (in_array('complete', $actionValues, true))
                    <form method="POST" action="{{ route('lawyer.appointments.complete', $appointment) }}">
                        @csrf
                        <button class="btn outline small" type="submit"><i class="fa-solid fa-flag-checkered"></i> Mark completed</button>
                    </form>
                @endif
                @if (in_array('cancel', $actionValues, true))
                    <form method="POST" action="{{ route('lawyer.appointments.cancel', $appointment) }}"
                          onsubmit="return confirm('Cancel this appointment?');">
                        @csrf
                        <button class="btn danger small" type="submit"><i class="fa-solid fa-ban"></i> Cancel</button>
                    </form>
                @endif
            </div>
        @else
            <p class="subtext" style="margin-top:20px;">
                This appointment is in a final state and can no longer be changed.
            </p>
        @endif
    </div>
</div>
@endsection
