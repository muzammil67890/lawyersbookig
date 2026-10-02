<?php

namespace App\Http\Requests\Appointments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 6 Part 2. Validates the shape of a reschedule request only
 * (a well-formed future-ish date + time). Whether the appointment
 * itself may be rescheduled right now, and whether the new slot is
 * actually free, is re-checked by AppointmentStatusService::reschedule()
 * against AppointmentAvailabilityService -- the same source of truth
 * booking uses -- never trusted from this validation alone.
 */
class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'appointment_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.now()->addDays((int) config('appointments.booking_window_days', 60))->toDateString(),
            ],
            'start_time' => ['required', 'date_format:H:i'],
        ];
    }
}
