@extends('layouts.minimal')

@section('title', 'My Appointments | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>My appointments</h1>
            <p>Appointment requests you've sent to lawyers on Legal Connect.</p>
        </div>
        <a class="btn outline" href="{{ route('customer.dashboard') }}"><i class="fa-solid fa-arrow-left"></i> Back to dashboard</a>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    <div class="row-actions" style="margin-bottom:18px;flex-wrap:wrap;gap:6px;">
        @php
            $tabs = [
                null => 'All',
                \App\Models\Appointment::FILTER_UPCOMING => 'Upcoming',
                'pending' => 'Pending',
                'confirmed' => 'Confirmed',
                'completed' => 'Completed',
                'rejected' => 'Rejected',
                'cancelled' => 'Cancelled',
                'rescheduled' => 'Rescheduled',
            ];
        @endphp
        @foreach ($tabs as $value => $label)
            <a class="btn small {{ $filter === $value ? 'gold' : 'outline' }}"
               href="{{ route('customer.appointments.index', $value ? ['filter' => $value] : []) }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="panel" style="padding:0;">
        @if ($appointments->isEmpty())
            <div style="padding:22px;">
                <p class="subtext">No appointments match this filter yet. Visit the <a href="{{ route('lawyers.index') }}">lawyer directory</a> to find someone to consult.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Lawyer</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($appointments as $appointment)
                            <tr>
                                <td>{{ $appointment->reference }}</td>
                                <td>{{ $appointment->lawyerProfile->display_name }}</td>
                                <td>{{ $appointment->service->name ?? '—' }}</td>
                                <td>{{ $appointment->appointment_date->format('M j, Y') }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($appointment->start_time)->format('g:i A') }}</td>
                                <td><span class="status {{ $appointment->status->value }}">{{ $appointment->status->label() }}</span></td>
                                <td><a class="btn outline small" href="{{ route('customer.appointments.show', $appointment) }}">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:18px 22px;">
                {{ $appointments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
