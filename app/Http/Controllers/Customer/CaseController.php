<?php

namespace App\Http\Controllers\Customer;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cases\CancelCaseRequest;
use App\Http\Requests\Cases\UpdateCaseRequest;
use App\Http\Requests\Customer\StoreCaseRequest;
use App\Models\LawyerProfile;
use App\Models\LegalCase;
use App\Services\LegalCaseStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Customer-facing case management (Phase 7, requirements #7, #12-#16).
 *
 * Every lawyer id this controller resolves goes through
 * LawyerProfile::searchable() (requirement #5: reuse, never re-derive
 * eligibility -- the same scope the Phase 5 directory and Phase 6
 * booking flow already use). Status changes never touch `status`
 * directly -- they are delegated entirely to LegalCaseStatusService,
 * the single place allowed to change it.
 */
class CaseController extends Controller
{
    public function __construct(private readonly LegalCaseStatusService $status)
    {
    }

    /** The authenticated customer's own cases -- never another customer's. */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $validStatus = CaseStatus::tryFrom((string) $status);

        $cases = $request->user()->casesAsCustomer()
            ->when($validStatus, fn ($query) => $query->status($validStatus))
            ->with(['lawyerProfile:id,display_name,title,city', 'service:id,name'])
            ->withCount('documents')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('customer.cases.index', ['cases' => $cases, 'status' => $validStatus]);
    }

    public function create(Request $request, int $lawyer): View
    {
        $this->authorize('create', LegalCase::class);

        $lawyerProfile = LawyerProfile::query()
            ->searchable()
            ->with(['services' => fn ($query) => $query->active()->select('services.id', 'services.name', 'services.slug')])
            ->findOrFail($lawyer);

        return view('customer.cases.create', ['lawyer' => $lawyerProfile]);
    }

    public function store(StoreCaseRequest $request, int $lawyer): RedirectResponse
    {
        $this->authorize('create', LegalCase::class);

        $lawyerProfile = LawyerProfile::query()->searchable()->findOrFail($lawyer);

        $service = $request->validated('service_id')
            ? $lawyerProfile->services()->active()->findOrFail($request->validated('service_id'))
            : null;

        $case = DB::transaction(function () use ($request, $lawyerProfile, $service) {
            return LegalCase::create([
                'case_number' => $this->generateCaseNumber(),
                'customer_id' => $request->user()->id,
                'lawyer_profile_id' => $lawyerProfile->id,
                'service_id' => $service?->id,
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
                'status' => CaseStatus::PENDING->value,
            ]);
        });

        $this->status->notifyAssigned($case);

        return redirect()->route('customer.cases.show', $case)
            ->with('status', "Case {$case->case_number} submitted. It is pending the lawyer's acceptance.");
    }

    public function show(Request $request, LegalCase $case): View
    {
        $this->authorize('view', $case);

        $case->load(['lawyerProfile:id,display_name,title,city', 'service:id,name', 'documents.uploader:id,name']);

        return view('customer.cases.show', [
            'case' => $case,
            'actions' => $this->status->availableActions($case, $request->user()),
        ]);
    }

    /** Only title/description/customer_notes -- never ownership, status, assignment, or the lawyer's private notes (requirement #14). */
    public function update(UpdateCaseRequest $request, LegalCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        // Phase 11: 'notes' is the lawyer's private notes -- a customer
        // request can never write it, even if a 'notes' key is smuggled
        // into the payload, because it is simply not in this ->only() list.
        $case->fill($request->safe()->only(['title', 'description', 'customer_notes']))->save();

        return redirect()->route('customer.cases.show', $case)->with('status', 'Case updated.');
    }

    public function cancel(CancelCaseRequest $request, LegalCase $case): RedirectResponse
    {
        $this->status->cancel($case, $request->user(), $request->validated('reason'));

        return redirect()->route('customer.cases.show', $case)
            ->with('status', "Case {$case->case_number} cancelled.");
    }

    private function generateCaseNumber(): string
    {
        do {
            $number = 'LC-C-'.now()->format('Y').'-'.random_int(10000, 99999);
        } while (LegalCase::where('case_number', $number)->exists());

        return $number;
    }
}
