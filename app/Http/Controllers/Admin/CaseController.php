<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCaseLawyerRequest;
use App\Http\Requests\Cases\CancelCaseRequest;
use App\Models\LawyerProfile;
use App\Models\LegalCase;
use App\Services\LegalCaseStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin case oversight (Phase 7, requirements #10-#11).
 *
 * Deliberately narrow: an admin may view every case, reassign it to a
 * different lawyer, close a completed case, cancel a case for
 * dispute/oversight reasons, and delete an already-cancelled,
 * document-free case. An admin cannot activate, progress, or complete
 * a case -- that operational work belongs to the assigned lawyer
 * (LegalCasePolicy enforces this, not this controller).
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
        $lawyerId = $request->query('lawyer_profile_id');
        $customerId = $request->query('customer_id');

        $cases = LegalCase::query()
            ->when($validStatus, fn ($query) => $query->status($validStatus))
            ->when($lawyerId, fn ($query) => $query->where('lawyer_profile_id', $lawyerId))
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->with(['customer:id,name', 'lawyerProfile:id,display_name'])
            ->withCount('documents')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.cases.index', ['cases' => $cases, 'status' => $validStatus]);
    }

    public function show(Request $request, LegalCase $case): View
    {
        $this->authorize('view', $case);

        $case->load(['customer:id,name,email', 'lawyerProfile:id,display_name', 'service:id,name', 'documents.uploader:id,name']);

        return view('admin.cases.show', ['case' => $case]);
    }

    public function assignLawyer(AssignCaseLawyerRequest $request, LegalCase $case): RedirectResponse
    {
        $lawyer = LawyerProfile::query()->searchable()->findOrFail($request->validated('lawyer_profile_id'));

        $this->status->reassignLawyer($case, $request->user(), $lawyer);

        return redirect()->route('admin.cases.show', $case)->with('status', "Case {$case->case_number} reassigned.");
    }

    public function close(Request $request, LegalCase $case): RedirectResponse
    {
        $this->status->close($case, $request->user());

        return redirect()->route('admin.cases.show', $case)->with('status', "Case {$case->case_number} closed.");
    }

    public function cancel(CancelCaseRequest $request, LegalCase $case): RedirectResponse
    {
        $this->status->cancel($case, $request->user(), $request->validated('reason'));

        return redirect()->route('admin.cases.show', $case)->with('status', "Case {$case->case_number} cancelled.");
    }

    /**
     * Requirement #10 ("separate ... deletion"): only a case that is
     * already cancelled and has no remaining documents may be deleted
     * at all -- this is a data-hygiene action for abandoned/erroneous
     * requests, never a way to erase a case with real history. Any
     * document still attached must be removed first, through the
     * normal document-delete flow, which is itself authorized and
     * audited per document.
     */
    public function destroy(Request $request, LegalCase $case): RedirectResponse
    {
        $this->authorize('delete', $case);

        if ($case->status !== CaseStatus::CANCELLED) {
            return back()->withErrors(['case' => 'Only a cancelled case may be deleted.']);
        }

        if ($case->documents()->exists()) {
            return back()->withErrors(['case' => 'Remove all documents from this case before deleting it.']);
        }

        DB::transaction(fn () => $case->delete());

        return redirect()->route('admin.cases.index')->with('status', "Case {$case->case_number} deleted.");
    }
}
