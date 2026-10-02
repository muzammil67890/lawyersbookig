<?php

namespace App\Http\Requests\Lawyers;

use App\Models\Specialization;
use App\Services\Matching\MatchCriteria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the GET query string of `/lawyers/match`.
 *
 * Nothing the client sends reaches a query except through these typed
 * rules and then through MatchCriteria (which re-validates its own
 * invariants). Unlike the directory, unknown service/specialization
 * IDs ARE rejected here: this endpoint is fed IDs taken from the same
 * public catalogue (the form's dropdowns, or the future Phase 11
 * assistant), so a bad ID is a client bug worth surfacing, and
 * services/specializations are public data -- no enumeration risk.
 */
class LawyerMatchRequest extends FormRequest
{
    /** On failure, send browsers back to the match form (not the site root). */
    protected $redirectRoute = 'lawyers.match';

    public function authorize(): bool
    {
        return true; // public, read-only, public data only
    }

    protected function prepareForValidation(): void
    {
        // Allow `?strict=budget` as well as `?strict[]=budget`.
        if (is_string($this->query('strict'))) {
            $this->merge(['strict' => [$this->query('strict')]]);
        }
    }

    public function rules(): array
    {
        return [
            'problem' => ['nullable', 'string', 'max:'.(int) config('lawyer_matching.problem.max_length', 500)],
            'service' => ['nullable', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'specialization' => ['nullable', 'integer', Rule::exists('specializations', 'id')],
            'city' => ['nullable', 'string', 'max:100'],
            'min_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            // `gte:min_budget` only when a minimum was sent (Laravel's
            // `gte` fails against an absent field).
            'max_budget' => array_values(array_filter([
                'nullable', 'numeric', 'min:0', 'max:9999999',
                $this->filled('min_budget') ? 'gte:min_budget' : null,
            ])),
            'min_experience' => ['nullable', 'integer', 'min:0', 'max:70'],
            'available_day' => ['nullable', 'integer', 'between:0,6'],
            'strict' => ['nullable', 'array', 'max:'.count(MatchCriteria::STRICT_OPTIONS)],
            'strict.*' => ['string', 'distinct', Rule::in(MatchCriteria::STRICT_OPTIONS)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('lawyer_matching.limits.max', 50)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('service') || ! $this->filled('specialization')) {
                return;
            }

            $belongs = Specialization::query()
                ->whereKey((int) $this->input('specialization'))
                ->where('service_id', (int) $this->input('service'))
                ->exists();

            if (! $belongs) {
                $validator->errors()->add('specialization', 'The selected specialization does not belong to the selected service.');
            }
        });
    }
}
