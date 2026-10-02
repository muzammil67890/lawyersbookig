@extends('layouts.minimal')

@section('title', 'Appointment '.$appointment->reference.' | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Appointment {{ $appointment->reference }}</h1>
            <p>Requested with {{ $appointment->lawyerProfile->display_name }}</p>
        </div>
        <a class="btn outline" href="{{ route('customer.appointments.index') }}"><i class="fa-solid fa-arrow-left"></i> All appointments</a>
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

        @if ($appointment->reason)
            <div style="margin-top:16px;">
                <span class="subtext">Notes you shared</span>
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
                <a href="{{ route('customer.appointments.show', $appointment->rescheduledFrom) }}">{{ $appointment->rescheduledFrom->reference }}</a>
            </div>
        @endif

        @if ($appointment->rescheduledTo)
            <div style="margin-top:16px;">
                <span class="subtext">Rescheduled to</span><br>
                <a href="{{ route('customer.appointments.show', $appointment->rescheduledTo) }}">{{ $appointment->rescheduledTo->reference }}</a>
            </div>
        @endif

        <div style="margin-top:16px;">
            <span class="subtext">Lawyer</span><br>
            <a href="{{ route('lawyers.show', $appointment->lawyerProfile->id) }}">{{ $appointment->lawyerProfile->display_name }}</a>
        </div>

        @if (in_array(\App\Enums\AppointmentAction::CANCEL, $actions, true) || in_array(\App\Enums\AppointmentAction::RESCHEDULE, $actions, true))
            <div class="row-actions" style="margin-top:22px;gap:8px;">
                @if (in_array(\App\Enums\AppointmentAction::RESCHEDULE, $actions, true))
                    <a class="btn outline small" href="{{ route('customer.appointments.reschedule.form', $appointment) }}">
                        <i class="fa-solid fa-calendar-days"></i> Reschedule
                    </a>
                @endif
                @if (in_array(\App\Enums\AppointmentAction::CANCEL, $actions, true))
                    <form method="POST" action="{{ route('customer.appointments.cancel', $appointment) }}"
                          onsubmit="return confirm('Cancel this appointment?');">
                        @csrf
                        <button class="btn danger small" type="submit"><i class="fa-solid fa-xmark"></i> Cancel</button>
                    </form>
                @endif
            </div>
        @else
            <p class="subtext" style="margin-top:20px;">
                This appointment is in a final state and can no longer be changed.
            </p>
        @endif
    </div>

    @if ($appointment->status->value === 'completed')
        <div class="panel" style="padding:22px;max-width:560px;margin-top:18px;">
            <h3 style="margin-bottom:10px;">Your review</h3>

            @if ($appointment->review)
                <span class="rating"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ $appointment->review->rating }}/5</span>
                @if ($appointment->review->comment)
                    <p style="margin-top:8px;">{{ $appointment->review->comment }}</p>
                @endif
                <div style="margin-top:14px;">
                    <a class="btn outline small" href="{{ route('customer.reviews.edit', $appointment->review) }}"><i class="fa-solid fa-pen"></i> Edit review</a>
                </div>
            @elseif (auth()->user()->can('create', [\App\Models\Review::class, $appointment]))
                <p class="subtext" style="margin-bottom:14px;">
                    Let other clients know how your consultation with {{ $appointment->lawyerProfile->display_name }} went.
                </p>
                <a class="btn gold" href="{{ route('customer.appointments.review.create', $appointment) }}"><i class="fa-solid fa-star"></i> Leave a review</a>
            @else
                <p class="subtext">You haven't left a review for this appointment.</p>
            @endif
        </div>
    @endif
</div>
@endsection
