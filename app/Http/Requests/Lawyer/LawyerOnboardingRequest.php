<?php

namespace App\Http\Requests\Lawyer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a lawyer's Phase 4 onboarding submission: the professional
 * profile fields, the CNIC/face verification images, and the
 * services/specializations the lawyer offers.
 *
 * `authorize()` only confirms the requester is an authenticated
 * lawyer -- the "have they already onboarded?" check is a business
 * rule the controller handles (and redirects on), not a validation
 * concern. Nothing here ever accepts a `status`, `verification_status`
 * or `role` field from the client: those are never part of these
 * rules, so no value the browser sends for them can reach the
 * database no matter what the request contains.
 */
class LawyerOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isLawyer() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'bio' => ['required', 'string', 'max:2000'],
            'years_of_experience' => ['required', 'integer', 'min:0', 'max:70'],
            'city' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'consultation_fee' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bar_council' => ['nullable', 'string', 'max:150'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'education' => ['nullable', 'string', 'max:1000'],

            'profile_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'cnic_image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'face_image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],

            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['integer', Rule::exists('services', 'id')],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['integer', Rule::exists('specializations', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'services.required' => 'Select at least one legal service you offer.',
            'cnic_image.required' => 'A CNIC image is required to submit your application for review.',
            'face_image.required' => 'A face photo is required to submit your application for review.',
        ];
    }
}
