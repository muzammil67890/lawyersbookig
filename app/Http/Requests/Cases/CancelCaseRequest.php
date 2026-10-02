<?php

namespace App\Http\Requests\Cases;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mirrors App\Http\Requests\Appointments\CancelAppointmentRequest.
 * Authorization for *whether this case may be cancelled by this user
 * right now* is LegalCaseStatusService's job (via LegalCasePolicy +
 * the CaseAction transition table) -- this class only validates the
 * optional reason text.
 */
class CancelCaseRequest extends FormRequest
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
