<?php

namespace App\Http\Requests\Appointments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 6 Part 2. Authorization for *whether this appointment may be
 * cancelled by this user right now* is AppointmentStatusService's job
 * (via AppointmentPolicy + the transition table) -- this class only
 * validates the optional reason text. `authorize()` only checks that
 * some authenticated user is making the request; route middleware
 * already guarantees a customer or lawyer role.
 */
class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
