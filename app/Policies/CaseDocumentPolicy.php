<?php

namespace App\Policies;

use App\Models\CaseDocument;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 7: server-side authorization for individual case documents.
 * A document's access rules are entirely inherited from the case it
 * belongs to -- rather than re-deriving "does this user own/get
 * assigned to this case" a second time here, every read ability
 * delegates to LegalCasePolicy through Gate::forUser(), so ownership
 * is defined in exactly one place (requirement #11's own principle,
 * applied to documents).
 *
 * Deletion is intentionally narrower than viewing: viewing follows the
 * case (customer, assigned lawyer, admin); deleting a document is
 * restricted to whoever actually uploaded it, or an admin -- a
 * customer must not be able to delete a lawyer's upload on their own
 * case, or vice versa, even though both parties can view it.
 */
class CaseDocumentPolicy
{
    public function view(User $user, CaseDocument $document): bool
    {
        return $document->case !== null && Gate::forUser($user)->allows('viewDocuments', $document->case);
    }

    public function download(User $user, CaseDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function delete(User $user, CaseDocument $document): bool
    {
        if (! $this->view($user, $document)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $document->uploaded_by !== null && $user->id === $document->uploaded_by;
    }
}
