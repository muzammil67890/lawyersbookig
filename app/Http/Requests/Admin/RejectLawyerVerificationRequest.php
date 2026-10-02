<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the rejection reason an admin must supply when rejecting a
 * lawyer's verification application. Authorization itself (must be an
 * admin, must be allowed to review this specific record) is handled
 * by the `role:admin` route middleware plus the LawyerVerificationPolicy
 * `review` check in the controller, not here.
 */
class RejectLawyerVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Please explain why this application is being rejected.',
            'rejection_reason.min' => 'Please give a rejection reason of at least 10 characters so the lawyer knows what to fix.',
        ];
    }
}
