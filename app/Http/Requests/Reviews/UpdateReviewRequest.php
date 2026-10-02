<?php

namespace App\Http\Requests\Reviews;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 9. Re-validates rating/comment on edit, identically to
 * StoreReviewRequest. Whether this user may edit *this* review at all
 * is ReviewPolicy::update(), checked by the controller via
 * $this->authorize() -- this class never accepts customer_id,
 * lawyer_profile_id, or appointment_id, so those relationships can
 * never be changed through this endpoint.
 */
class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('comment')) {
            $this->merge(['comment' => trim((string) $this->input('comment')) ?: null]);
        }
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => [
                'nullable',
                'string',
                'min:'.config('reviews.comment_min_length', 3),
                'max:'.config('reviews.comment_max_length', 2000),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Please select a star rating from 1 to 5.',
            'rating.between' => 'Rating must be between 1 and 5 stars.',
            'comment.min' => 'Your review is too short -- please share a little more detail.',
            'comment.max' => 'Your review is too long (maximum :max characters).',
        ];
    }
}
