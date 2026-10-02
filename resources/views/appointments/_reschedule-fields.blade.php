{{--
    Shared by customer and lawyer reschedule views. Reuses the exact
    date/time slot-picker pattern from customer/appointments/create.blade.php
    (Phase 6 Part 1) rather than inventing a second one -- only the
    slots endpoint and submit action differ, both passed in by the
    including view.
--}}
<form method="POST" action="{{ $submitUrl }}" id="reschedule-form" novalidate>
    @csrf

    <div class="two-field">
        <div class="field {{ $errors->has('appointment_date') ? 'invalid' : '' }}">
            <label for="appointment_date">New date</label>
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

    <div class="error" style="display:block;" id="slots-status"></div>

    <button class="btn gold full" type="submit"><i class="fa-solid fa-calendar-days"></i> Confirm new time</button>
</form>

@push('scripts')
<script>
(function () {
    var dateInput = document.getElementById('appointment_date');
    var timeSelect = document.getElementById('start_time');
    var status = document.getElementById('slots-status');
    if (! dateInput || ! timeSelect) return;

    var slotsUrl = @json($slotsUrl);

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
