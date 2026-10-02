@extends('layouts.minimal')

@section('title', 'Request an Appointment | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Request an appointment</h1>
            <p>With {{ $lawyer->display_name }}{{ $lawyer->title ? ' &mdash; '.$lawyer->title : '' }}</p>
        </div>
        <a class="btn outline" href="{{ route('lawyers.show', $lawyer->id) }}"><i class="fa-solid fa-arrow-left"></i> Back to profile</a>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    <div class="panel" style="padding:26px;max-width:620px;">
        @if ($lawyer->services->isEmpty())
            <p class="subtext">This lawyer has not listed any bookable services yet, so appointment requests cannot be submitted right now.</p>
        @else
            <form method="POST" action="{{ route('customer.appointments.store', $lawyer->id) }}" id="booking-form" novalidate>
                @csrf

                <div class="field {{ $errors->has('service_id') ? 'invalid' : '' }}">
                    <label for="service_id">Service</label>
                    <select id="service_id" name="service_id" required>
                        <option value="">Select a service</option>
                        @foreach ($lawyer->services as $service)
                            <option value="{{ $service->id }}" {{ (string) old('service_id') === (string) $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                        @endforeach
                    </select>
                    <div class="error">{{ $errors->first('service_id') }}</div>
                </div>

                <div class="two-field">
                    <div class="field {{ $errors->has('appointment_date') ? 'invalid' : '' }}">
                        <label for="appointment_date">Date</label>
                        <input id="appointment_date" name="appointment_date" type="date"
                               min="{{ $minDate }}" max="{{ $maxDate }}" value="{{ old('appointment_date') }}" required>
                        <div class="error">{{ $errors->first('appointment_date') }}</div>
                    </div>
                    <div class="field {{ $errors->has('start_time') ? 'invalid' : '' }}">
                        <label for="start_time">Available times</label>
                        <select id="start_time" name="start_time" required>
                            <option value="">Choose a date first</option>
                        </select>
                        <div class="error">{{ $errors->first('start_time') }}</div>
                    </div>
                </div>

                <div class="field {{ $errors->has('consultation_type') ? 'invalid' : '' }}">
                    <label for="consultation_type">Consultation type</label>
                    <select id="consultation_type" name="consultation_type" required>
                        @foreach ($consultationTypes as $type)
                            <option value="{{ $type->value }}" {{ old('consultation_type') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <div class="error">{{ $errors->first('consultation_type') }}</div>
                </div>

                <div class="field {{ $errors->has('reason') ? 'invalid' : '' }}">
                    <label for="reason">What would you like to discuss? <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                    <textarea id="reason" name="reason" rows="4">{{ old('reason') }}</textarea>
                    <div class="error">{{ $errors->first('reason') }}</div>
                </div>

                <div class="error" style="display:block;" id="slots-status"></div>

                <button class="btn gold full" type="submit"><i class="fa-solid fa-calendar-check"></i> Request appointment</button>
                <p class="subtext" style="margin-top:10px;">This sends a request &mdash; it is not confirmed until the lawyer accepts it.</p>
            </form>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var dateInput = document.getElementById('appointment_date');
    var timeSelect = document.getElementById('start_time');
    var status = document.getElementById('slots-status');
    if (! dateInput || ! timeSelect) return;

    var slotsUrl = @json(route('customer.appointments.slots', $lawyer->id));

    function loadSlots() {
        var date = dateInput.value;
        timeSelect.innerHTML = '';
        status.textContent = '';

        if (! date) {
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Choose a date first';
            timeSelect.appendChild(placeholder);
            return;
        }

        var loading = document.createElement('option');
        loading.value = '';
        loading.textContent = 'Loading available times…';
        timeSelect.appendChild(loading);

        fetch(slotsUrl + '?date=' + encodeURIComponent(date), {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                timeSelect.innerHTML = '';
                var slots = (data && data.slots) || [];

                if (slots.length === 0) {
                    var none = document.createElement('option');
                    none.value = '';
                    none.textContent = 'No available times on this date';
                    timeSelect.appendChild(none);
                    return;
                }

                var pick = document.createElement('option');
                pick.value = '';
                pick.textContent = 'Select a time';
                timeSelect.appendChild(pick);

                slots.forEach(function (slot) {
                    var option = document.createElement('option');
                    option.value = slot.start;
                    option.textContent = slot.start + ' – ' + slot.end;
                    timeSelect.appendChild(option);
                });
            })
            .catch(function () {
                timeSelect.innerHTML = '';
                var errorOption = document.createElement('option');
                errorOption.value = '';
                errorOption.textContent = 'Could not load available times';
                timeSelect.appendChild(errorOption);
                status.textContent = 'Could not load available times. Please try again.';
            });
    }

    dateInput.addEventListener('change', loadSlots);
})();
</script>
@endpush
