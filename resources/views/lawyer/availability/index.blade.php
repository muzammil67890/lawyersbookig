@extends('layouts.minimal')

@section('title', 'Manage Availability | Legal Connect')

@php
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
@endphp

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Manage your availability</h1>
            <p>Define the weekly hours (or one-off dates) customers can request an appointment with you.</p>
        </div>
        <div style="display:flex;gap:10px;">
            <a class="btn outline" href="{{ route('lawyer.dashboard') }}"><i class="fa-solid fa-arrow-left"></i> Back to dashboard</a>
            <a class="btn gold" href="{{ route('lawyer.availability.create') }}"><i class="fa-solid fa-plus"></i> Add availability</a>
        </div>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    @if (! $profile->isEligible())
        <div class="demo-note" style="margin-bottom:18px;">
            You can configure availability now, but customers can only book you once your profile is approved,
            active and public.
        </div>
    @endif

    <div class="panel" style="padding:0;">
        @if ($availabilities->isEmpty())
            <div style="padding:22px;">
                <p class="subtext">You haven't added any availability yet. Add a weekly recurring window or a one-off date to get started.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($availabilities as $availability)
                            <tr>
                                <td>
                                    @if (! is_null($availability->day_of_week))
                                        <strong>{{ $dayNames[$availability->day_of_week] }}</strong>
                                        <span>Every week</span>
                                    @else
                                        <strong>{{ $availability->specific_date->format('D, M j, Y') }}</strong>
                                        <span>One-off date</span>
                                    @endif
                                </td>
                                <td>{{ \Illuminate\Support\Carbon::parse($availability->start_time)->format('g:i A') }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($availability->end_time)->format('g:i A') }}</td>
                                <td>{{ $availability->is_recurring ? 'Recurring' : 'One-off' }}</td>
                                <td>
                                    <span class="status {{ $availability->is_available ? 'active' : 'suspended' }}">
                                        {{ $availability->is_available ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn outline small" href="{{ route('lawyer.availability.edit', $availability) }}">Edit</a>
                                        <form method="POST" action="{{ route('lawyer.availability.toggle', $availability) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn outline small" type="submit">
                                                {{ $availability->is_available ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('lawyer.availability.destroy', $availability) }}"
                                              onsubmit="return confirm('Remove this availability window?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn danger small" type="submit">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
