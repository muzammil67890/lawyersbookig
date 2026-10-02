<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 9, requirement #19: deliberately narrow admin oversight of
 * reviews, modeled on Admin\CaseController's approach -- an admin can
 * inspect any review and move it between published/rejected to police
 * abusive or spam content, but can never edit a review's rating or
 * comment and can never hard-delete one (ReviewPolicy grants neither
 * ability to admins; see ReviewService::reject()/restore()). This is
 * not a pre-publication moderation queue -- reviews publish
 * immediately (see ReviewService) -- it is after-the-fact abuse
 * handling only.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Review::class);

        $status = $request->query('status');
        $validStatus = $status ? ReviewStatus::tryFrom((string) $status) : null;

        $reviews = Review::query()
            ->when($validStatus, fn ($query) => $query->where('status', $validStatus->value))
            ->with(['customer:id,name', 'lawyerProfile:id,display_name', 'appointment:id,reference'])
            ->latest()
            ->paginate((int) config('reviews.admin_page_size', 20))
            ->withQueryString();

        return view('admin.reviews.index', ['reviews' => $reviews, 'status' => $validStatus]);
    }

    public function show(Review $review): View
    {
        $this->authorize('view', $review);

        $review->load([
            'customer:id,name,email',
            'lawyerProfile:id,display_name',
            'appointment:id,reference,appointment_date,status',
        ]);

        return view('admin.reviews.show', ['review' => $review]);
    }

    public function reject(Request $request, Review $review): RedirectResponse
    {
        $this->reviews->reject($review, $request->user());

        return redirect()->route('admin.reviews.show', $review)
            ->with('status', 'Review hidden from public view.');
    }

    public function restore(Request $request, Review $review): RedirectResponse
    {
        $this->reviews->restore($review, $request->user());

        return redirect()->route('admin.reviews.show', $review)
            ->with('status', 'Review restored to public view.');
    }
}
