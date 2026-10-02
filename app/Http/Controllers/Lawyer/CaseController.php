<?php

namespace App\Http\Controllers\Lawyer;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cases\CancelCaseRequest;
use App\Http\Requests\Cases\UpdateCaseRequest;
use App\Models\LegalCase;
use App\Services\LegalCaseStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lawyer-facing case management (Phase 7, requirements #8, #12-#16).
 *
 * Every record is scoped to $request->user()->lawyerProfile inside the
 * controller and additionally gated by LegalCasePolicy -- the same
 * two-layer pattern Lawyer\AppointmentController already uses. A
 * lawyer never sees or acts on a case assigned to a different lawyer.
 */
class CaseController extends Controller
{
    public function __construct(private readonly LegalCaseStatusService $status)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $validStatus = CaseStatus::tryFrom((string) $status);

        $profile = $request->user()->lawyerProfile;

        $cases = $profile
            ? $profile->cases()
                ->when($validStatus, fn ($query) => $query->status($validStatus))
                ->with(['customer:id,name', 'service:id,name'])
                ->withCount('documents')
                ->orderByDesc('created_at')
                ->paginate(10)
                ->withQueryString()
            : LegalCase::query()->whereNull('id')->paginate(10);

        return view('lawyer.cases.index', ['cases' => $cases, 'status' => $validStatus]);
    }

    public function show(Request $request, LegalCase $case): View
    {
        $this->authorize('view', $case);

        $case->load(['customer:id,name,email', 'service:id,name', 'documents.uploader:id,name']);

        return view('lawyer.cases.show', [
            'case' => $case,
            'actions' => $this->status->availableActions($case, $request->user()),
        ]);
    }

    /** Only notes (the lawyer's own private notes) -- a lawyer never edits the customer's title/description or the customer's private notes (requirement #14). */
    public function update(UpdateCaseRequest $request, LegalCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        // Phase 11: 'customer_notes' is the customer's private notes --
        // never readable/writable here, because it is simply not in this
        // ->only() list, even if a lawyer's request payload contains it.
        $case->fill($request->safe()->only(['notes']))->save();

        return redirect()->route('lawyer.cases.show', $case)->with('status', 'Case notes updated.');
    }

    public function activate(Request $request, LegalCase $case): RedirectResponse
    {
        $this->status->activate($case, $request->user());

        return redirect()->route('lawyer.cases.show', $case)->with('status', "Case {$case->case_number} activated.");
    }

    public function startProgress(Request $request, LegalCase $case): RedirectResponse
    {
        $this->status->startProgress($case, $request->user());

        return redirect()->route('lawyer.cases.show', $case)->with('status', "Case {$case->case_number} moved to in progress.");
    }

    public function complete(Request $request, LegalCase $case): RedirectResponse
    {
        $this->status->complete($case, $request->user());

        return redirect()->route('lawyer.cases.show', $case)->with('status', "Case {$case->case_number} marked completed.");
    }

    public function close(Request $request, LegalCase $case): RedirectResponse
    {
        $this->status->close($case, $request->user());

        return redirect()->route('lawyer.cases.show', $case)->with('status', "Case {$case->case_number} closed.");
    }

    public function cancel(CancelCaseRequest $request, LegalCase $case): RedirectResponse
    {
        $this->status->cancel($case, $request->user(), $request->validated('reason'));

        return redirect()->route('lawyer.cases.show', $case)->with('status', "Case {$case->case_number} cancelled.");
    }
}
