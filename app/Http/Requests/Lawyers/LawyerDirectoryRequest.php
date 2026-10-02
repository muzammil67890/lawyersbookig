<?php

namespace App\Http\Requests\Lawyers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the public `/lawyers` directory's GET query string. This is
 * the only thing standing between arbitrary URL parameters and the
 * query builder in `LawyerDirectoryController`, so every filter the
 * controller reads is validated here first -- typed and length/range
 * checked, never taken as a raw string into a query clause.
 *
 * `sort` is deliberately validated against a fixed allow-list
 * (`SORTS` in the controller) rather than accepted as free text; the
 * controller never passes a client-supplied string into `orderBy()`.
 *
 * Filter values that don't match a real record (an unknown service
 * slug, a city nobody is in) are intentionally *not* rejected with a
 * validation error -- they just naturally produce zero results. That
 * avoids using validation errors as an oracle for "which slugs/cities
 * exist", and keeps a stale/bookmarked/shared URL from breaking with a
 * 422 instead of showing an empty result set.
 */
class LawyerDirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'service' => ['nullable', 'string', 'max:150'],
            'specialization' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'min_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            // `gte:min_fee` is only applied when a min_fee was actually
            // sent: Laravel's `gte` fails when the compared field is
            // absent, which used to reject a lone `?max_fee=3000`.
            'max_fee' => array_values(array_filter([
                'nullable', 'numeric', 'min:0', 'max:9999999',
                $this->filled('min_fee') ? 'gte:min_fee' : null,
            ])),
            'min_experience' => ['nullable', 'integer', 'min:0', 'max:70'],
            'sort' => ['nullable', 'string', Rule::in(['name', 'fee', 'experience', 'recent'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
