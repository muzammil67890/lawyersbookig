<?php

namespace App\Http\Requests\Customer;

use App\Enums\ConsultationType;
use App\Models\LawyerProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a customer's appointment request (Phase 6 Part 1,
 * requirements #12-#17).
 *
 * The lawyer is deliberately NOT a field on this request: it comes
 * from the `{lawyer}` route segment (see routes/web.php and
 * Customer\AppointmentController), exactly like
 * LawyerDirectoryController::show(). This request re-resolves that
 * same id through LawyerProfile::searchable() itself (see
 * withValidator()) so a pending/rejected/inactive/suspended lawyer id
 * in the URL fails validation here with a clear error, in addition to
 * the controller's own defensive searchable()->findOrFail() lookup.
 *
 * `customer_id` is never a field here at all -- the controller derives
 * the customer exclusively from auth()->user().
 */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() ?? false;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer'],
            'appointment_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.now()->addDays((int) config('appointments.booking_window_days', 60))->toDateString(),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'consultation_type' => ['required', Rule::in(array_map(fn (ConsultationType $c) => $c->value, ConsultationType::cases()))],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lawyer = LawyerProfile::query()->searchable()->find($this->route('lawyer'));

            if (! $lawyer) {
                $validator->errors()->add('lawyer', 'This lawyer is not currently available for booking.');

                return;
            }

            if ($this->filled('service_id')
                && ! $lawyer->services()->active()->whereKey($this->input('service_id'))->exists()) {
                $validator->errors()->add('service_id', 'The selected service is not offered by this lawyer.');
            }

            // A same-day booking whose start time has already passed
            // is still structurally "today" and would pass the
            // date-only checks above; reject it explicitly using the
            // application timezone rather than the client's clock.
            if ($this->filled('appointment_date') && $this->filled('start_time')
                && $this->input('appointment_date') === now()->toDateString()) {
                $requestedStart = \Illuminate\Support\Carbon::parse($this->input('appointment_date').' '.$this->input('start_time'));

                if ($requestedStart->lessThanOrEqualTo(now())) {
                    $validator->errors()->add('start_time', 'The selected time has already passed.');
                }
            }
        });
    }
}
