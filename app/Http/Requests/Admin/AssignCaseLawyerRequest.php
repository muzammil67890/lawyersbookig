<?php

namespace App\Http\Requests\Admin;

use App\Models\LawyerProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an admin's request to (re)assign a case to a different
 * lawyer (requirement #9/#11). `lawyer_profile_id` is re-resolved
 * through LawyerProfile::searchable() in withValidator() -- exactly
 * like StoreAppointmentRequest/StoreCaseRequest resolve their own
 * {lawyer} route parameter -- so an id that exists but belongs to a
 * pending/rejected/inactive/suspended profile is rejected here, not
 * just left to a bare `exists:lawyer_profiles,id` rule.
 */
class AssignCaseLawyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'lawyer_profile_id' => ['required', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('lawyer_profile_id')
                && ! LawyerProfile::query()->searchable()->whereKey($this->input('lawyer_profile_id'))->exists()) {
                $validator->errors()->add('lawyer_profile_id', 'That lawyer is not currently eligible to take on cases.');
            }
        });
    }
}
