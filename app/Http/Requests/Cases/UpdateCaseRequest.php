<?php

namespace App\Http\Requests\Cases;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the handful of fields a case's parties may ever edit
 * (requirement #14). Authorization for *whether this case may be
 * edited by this user right now* is LegalCasePolicy::update() --
 * checked by the controller via $this->authorize() -- this class only
 * validates shape/length.
 *
 * Deliberately absent from rules(): customer_id, lawyer_profile_id,
 * status, case_number, opened_at, closed_at, cancellation_reason. None
 * of those are ever accepted from request input; status changes go
 * through the dedicated activate/start-progress/complete/close/cancel
 * routes and LegalCaseStatusService, and reassignment goes through the
 * separate admin-only assign-lawyer route. Each controller
 * additionally picks only the subset of validated() fields its role is
 * allowed to touch (e.g. the lawyer controller ignores
 * title/description even though they pass validation here) -- this
 * request only bounds shape, not per-role write access.
 *
 * Phase 11: `notes` is the LAWYER's private notes and `customer_notes`
 * is the customer's private notes -- neither party can read or write
 * the other's. Both fields validate here (shape only, same as
 * title/description), but the customer controller's
 * ->only(['title', 'description', 'customer_notes']) never reads
 * 'notes' out of validated(), and the lawyer controller's
 * ->only(['notes']) never reads 'customer_notes' -- so a smuggled
 * 'notes' value from a customer request (or 'customer_notes' from a
 * lawyer request) passes validation but is silently dropped by the
 * controller before it ever reaches the model. That per-role
 * ->only() filter, not this request, is what actually enforces the
 * privacy boundary.
 */
class UpdateCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'customer_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
