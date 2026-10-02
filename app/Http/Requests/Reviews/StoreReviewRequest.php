<?php

namespace App\Http\Requests\Reviews;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 9. Validates only the review's own content (rating/comment).
 *
 * `customer_id`, `lawyer_profile_id`, and `appointment_id` are
 * deliberately not fields here at all -- Customer\ReviewController
 * derives the reviewer from auth()->user() and the reviewed lawyer
 * from the `{appointment}` route model exclusively (see
 * ReviewService::create()). Whether this appointment may be reviewed
 * by this user at all is ReviewPolicy's job, checked by the controller
 * via $this->authorize() -- this class only validates the submitted
 * rating/comment, matching the StoreCaseDocumentRequest/
 * UpdateCaseRequest convention.
 */
class StoreReviewRequest extends FormRequest
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
