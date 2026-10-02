<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Public self-registration. `role` is intentionally restricted to a
 * closed allow-list of `customer`/`lawyer` -- ADMIN can never be
 * created this way, and the validated() output is the only thing the
 * controller ever passes to User::create(), so nothing outside these
 * rules can reach the database no matter what the request contains.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
            // Public registration may only ever create a customer or a
            // lawyer account. 'admin' (or anything else) fails validation
            // and never reaches the controller.
            'role' => ['required', Rule::in(['customer', 'lawyer'])],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Please choose a valid account type.',
        ];
    }
}
