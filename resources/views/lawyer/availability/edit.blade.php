@extends('layouts.minimal')

@section('title', 'Edit Availability | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Edit availability</h1>
            <p>Choose either a weekly recurring weekday, or a specific one-off date &mdash; not both.</p>
        </div>
        <a class="btn outline" href="{{ route('lawyer.availability.index') }}"><i class="fa-solid fa-arrow-left"></i> Back to availability</a>
    </div>

    <div class="panel" style="padding:26px;max-width:560px;">
        <form method="POST" action="{{ route('lawyer.availability.update', $availability) }}" novalidate>
            @csrf
            @method('PUT')

            <div class="field {{ $errors->has('day_of_week') ? 'invalid' : '' }}">
                <label for="day_of_week">Recurring weekday</label>
                <select id="day_of_week" name="day_of_week">
                    <option value="">&mdash; Not recurring &mdash;</option>
                    @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $index => $day)
                        <option value="{{ $index }}" {{ old('day_of_week', $availability->day_of_week) == $index ? 'selected' : '' }}>{{ $day }}</option>
                    @endforeach
                </select>
                <div class="error">{{ $errors->first('day_of_week') }}</div>
            </div>

            <div class="field {{ $errors->has('specific_date') ? 'invalid' : '' }}">
                <label for="specific_date">Or a specific one-off date</label>
                <input id="specific_date" name="specific_date" type="date" min="{{ now()->toDateString() }}"
                       value="{{ old('specific_date', optional($availability->specific_date)->toDateString()) }}">
                <div class="error">{{ $errors->first('specific_date') }}</div>
            </div>

            <div class="two-field">
                <div class="field {{ $errors->has('start_time') ? 'invalid' : '' }}">
                    <label for="start_time">Start time</label>
                    <input id="start_time" name="start_time" type="time"
                           value="{{ old('start_time', \Illuminate\Support\Carbon::parse($availability->start_time)->format('H:i')) }}" required>
                    <div class="error">{{ $errors->first('start_time') }}</div>
                </div>
                <div class="field {{ $errors->has('end_time') ? 'invalid' : '' }}">
                    <label for="end_time">End time</label>
                    <input id="end_time" name="end_time" type="time"
                           value="{{ old('end_time', \Illuminate\Support\Carbon::parse($availability->end_time)->format('H:i')) }}" required>
                    <div class="error">{{ $errors->first('end_time') }}</div>
                </div>
            </div>

            <div class="form-inline">
                <label><input type="checkbox" name="is_available" value="1" {{ old('is_available', $availability->is_available) ? 'checked' : '' }}> Active (bookable)</label>
            </div>

            <button class="btn gold full" type="submit"><i class="fa-solid fa-check"></i> Save changes</button>
        </form>
    </div>
</div>
@endsection
