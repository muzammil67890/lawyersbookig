<?php

namespace App\Http\Requests\Cases;

use App\Enums\CaseDocumentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a case document upload (requirements #20, #25).
 *
 * `mimes:` in Laravel checks the file's actual detected content
 * against the allow-listed extensions (via Symfony's MIME-type
 * guesser), not the client-supplied Content-Type header or the
 * client-supplied filename's extension alone -- so this is genuine
 * server-side validation, not a trust-the-browser check. The allowed
 * list and size cap both come from config/cases.php, matching the
 * config/appointments.php convention rather than hardcoding here.
 *
 * Authorization for *whether this user may upload to this case at
 * all* is LegalCasePolicy::uploadDocuments(), checked by the
 * controller via $this->authorize() against the case from the route.
 */
class StoreCaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document' => [
                'required',
                'file',
                'mimes:'.implode(',', config('cases.document_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'])),
                'max:'.config('cases.document_max_kb', 10240),
            ],
            'category' => ['nullable', Rule::in(array_map(fn (CaseDocumentCategory $c) => $c->value, CaseDocumentCategory::cases()))],
        ];
    }
}
