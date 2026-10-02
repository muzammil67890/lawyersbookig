<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\StoreReviewRequest;
use App\Http\Requests\Reviews\UpdateReviewRequest;
use App\Models\Appointment;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 9: customer-facing review submission, editing, withdrawal, and
 * review history. Modeled directly on Customer\CaseController --
 * ReviewPolicy is the single source of truth for every ownership/
 * eligibility check here, and every write goes through ReviewService,
 * never touching the `reviews` table directly.
 *
 * `{appointment}` is resolved via Laravel's implicit route-model
 * binding from the route wildcard (see routes/web.php) and is never
 * trusted as "belongs to this customer" merely because the route sits
 * under `/customer` -- ReviewPolicy::create() re-derives ownership
 * from the appointment row itself on every request.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    /** The authenticated customer's own submitted reviews -- never another customer's. */
    public function index(Request $request): View
    {
        $reviews = $request->user()->reviews()
            ->with(['lawyerProfile:id,display_name,title', 'appointment:id,reference,appointment_date'])
            ->latest()
            ->paginate((int) config('reviews.customer_page_size', 10))
            ->withQueryString();

        return view('customer.reviews.index', ['reviews' => $reviews]);
    }

    /**
     * The review form for one specific completed appointment.
     * ReviewPolicy::create() covers every eligibility rule (ownership,
     * COMPLETED status, not already reviewed, lawyer not self) -- this
     * action does not re-derive any of it.
     */
    public function create(Request $request, Appointment $appointment): View
    {
        $this->authorize('create', [Review::class, $appointment]);

        $appointment->load('lawyerProfile:id,display_name,title');

        return view('customer.reviews.create', ['appointment' => $appointment]);
    }

    public function store(StoreReviewRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('create', [Review::class, $appointment]);

        $review = $this->reviews->create(
            $appointment,
            $request->user(),
            (int) $request->validated('rating'),
            $request->validated('comment'),
        );

        return redirect()->route('customer.appointments.show', $appointment)
            ->with('status', "Thanks -- your review of {$review->lawyerProfile->display_name} has been published.");
    }

    public function edit(Review $review): View
    {
        $this->authorize('update', $review);

        $review->load(['lawyerProfile:id,display_name', 'appointment:id,reference']);

        return view('customer.reviews.edit', ['review' => $review]);
    }

    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $this->reviews->update(
            $review,
            $request->user(),
            (int) $request->validated('rating'),
            $request->validated('comment'),
        );

        return redirect()->route('customer.reviews.index')->with('status', 'Your review has been updated.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $this->reviews->delete($review, request()->user());

        return redirect()->route('customer.reviews.index')->with('status', 'Your review has been removed.');
    }
}
