<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cases\StoreCaseDocumentRequest;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\LawyerFileUploadService;
use App\Services\LegalCaseStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Secure case-document upload/download/delete (Phase 7, requirements
 * #17-#25), shared across the customer/lawyer/admin route prefixes --
 * mirroring how VerificationDocumentController is shared between the
 * lawyer and admin prefixes in Phase 4.
 *
 * Every action is reached as `/cases/{case}/documents/...` and every
 * method re-checks that the {document} route-bound model actually
 * belongs to the {case} route-bound model before doing anything else
 * (requirement #21 point 4) -- a request combining a case the caller
 * can see with a document id from a different case 404s exactly like
 * a nonexistent document, never leaking whether that document id
 * exists elsewhere. Ownership/role authorization for both the case and
 * the document is then re-checked again through
 * LegalCasePolicy/CaseDocumentPolicy -- URL prefix alone never implies
 * authorization (requirement #12's principle, applied to documents).
 *
 * Files are never stored under the client-supplied filename or in a
 * publicly reachable directory: LawyerFileUploadService (reused as-is;
 * its logic is already generic, not lawyer-specific) generates a fresh
 * UUID filename and writes to the private 'local' disk, so the only
 * way to reach a file's bytes is through download() below.
 */
class CaseDocumentController extends Controller
{
    public function store(
        StoreCaseDocumentRequest $request,
        LegalCase $case,
        LawyerFileUploadService $uploader,
        LegalCaseStatusService $status,
    ): RedirectResponse {
        $this->authorize('uploadDocuments', $case);

        $file = $request->file('document');
        $storedPath = null;

        try {
            $document = DB::transaction(function () use ($request, $case, $uploader, $file, &$storedPath) {
                $storedPath = $uploader->store($file, "case-documents/{$case->id}", 'local');

                return CaseDocument::create([
                    'case_id' => $case->id,
                    'uploaded_by' => $request->user()->id,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'category' => $request->validated('category'),
                ]);
            });
        } catch (Throwable $exception) {
            // The database write failed after the file was already
            // written to disk (or vice versa mid-transaction) -- clean
            // up the orphaned file rather than leaving it unreferenced
            // on the private disk (requirement #30: no partial state).
            if ($storedPath) {
                $uploader->delete($storedPath, 'local');
            }

            throw $exception;
        }

        $status->notifyDocumentUploaded($case, $request->user());

        return back()->with('status', "Document \"{$document->original_filename}\" uploaded.");
    }

    public function download(Request $request, LegalCase $case, CaseDocument $document): StreamedResponse
    {
        abort_unless($document->case_id === $case->id, 404);

        $this->authorize('download', $document);

        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        // Forced attachment (never an inline response) so a browser
        // never renders an uploaded file's content in the app's own
        // origin -- closes the stored-content-type/XSS angle in
        // requirement #25 regardless of what a client claimed the
        // MIME type was at upload time.
        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function destroy(Request $request, LegalCase $case, CaseDocument $document): RedirectResponse
    {
        abort_unless($document->case_id === $case->id, 404);

        $this->authorize('delete', $document);

        $path = $document->stored_path;
        $name = $document->original_filename;

        DB::transaction(fn () => $document->delete());

        // The database row (the actual access-control boundary) is
        // already gone by this point. A failure to also remove the
        // physical file leaves an orphaned, inaccessible-through-the-app
        // blob on the private disk -- wasted storage, not a data leak
        // -- so it is reported, not surfaced as a failed deletion
        // (requirement #23: no silently inconsistent state, but also
        // no false "deletion failed" to the user when the record is
        // in fact gone).
        try {
            Storage::disk('local')->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }

        return back()->with('status', "Document \"{$name}\" deleted.");
    }
}
