<?php

namespace App\Http\Requests\Customer;

use App\Models\LawyerProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a customer's request to open a new case with a specific
 * lawyer (Phase 7, requirement #7), modeled on
 * App\Http\Requests\Customer\StoreAppointmentRequest.
 *
 * The lawyer is deliberately NOT a field on this request: it comes
 * from the `{lawyer}` route segment (see routes/web.php and
 * Customer\CaseController), exactly like the appointment booking flow.
 * This request re-resolves that same id through LawyerProfile::searchable()
 * itself (see withValidator()) so a pending/rejected/inactive/suspended
 * lawyer id in the URL fails validation here in addition to the
 * controller's own defensive searchable()->findOrFail() lookup.
 *
 * `customer_id` is never a field here at all -- the controller derives
 * the customer exclusively from auth()->user().
 */
class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'service_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lawyer = LawyerProfile::query()->searchable()->find($this->route('lawyer'));

            if (! $lawyer) {
                $validator->errors()->add('lawyer', 'This lawyer is not currently available to take on new cases.');

                return;
            }

            if ($this->filled('service_id')
                && ! $lawyer->services()->active()->whereKey($this->input('service_id'))->exists()) {
                $validator->errors()->add('service_id', 'The selected service is not offered by this lawyer.');
            }
        });
    }
}
