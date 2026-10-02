<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new message body (Phase 8, requirement #13).
 *
 * Deliberately the ONLY field this request accepts is `body`. There is
 * no `conversation_id`, `sender_id`, or `read_at` field here at all --
 * the conversation comes from the `{conversation}` route binding and
 * the sender exclusively from auth()->user() (see
 * Customer/Lawyer\MessagingController + MessagingService), so nothing
 * ownership-related can ever be mass-assigned from this request.
 *
 * Real authorization (is this user a party to this conversation) is
 * ConversationPolicy's job, checked by the controller via
 * $this->authorize() before this request's validated data is ever
 * used -- authorize() here only confirms a request is well-formed
 * enough to reach that check.
 */
class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'body' => is_string($this->input('body')) ? trim($this->input('body')) : $this->input('body'),
        ]);
    }

    public function rules(): array
    {
        return [
            // `required` already rejects '' -- prepareForValidation() trims first,
            // so a whitespace-only submission ("   ") fails this too.
            'body' => ['required', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Type a message before sending.',
            'body.max' => 'Messages can be at most 4000 characters.',
        ];
    }
}
