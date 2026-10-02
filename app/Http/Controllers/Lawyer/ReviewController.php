<?php

namespace App\Http\Controllers\Lawyer;

use App\Http\Controllers\Controller;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Phase 9: read-only reputation view for the authenticated lawyer.
 * Every review shown is scoped to $request->user()->lawyerProfile,
 * the same pattern Lawyer\CaseController and Lawyer\AppointmentController
 * already use -- a lawyer never sees another lawyer's reviews.
 *
 * A lawyer has no write access to reviews at all (cannot create, edit,
 * or moderate one -- see ReviewPolicy), so this controller only ever
 * reads.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function index(Request $request): View
    {
        $profile = $request->user()->lawyerProfile;

        if (! $profile) {
            $pageSize = (int) config('reviews.lawyer_page_size', 10);

            return view('lawyer.reviews.index', [
                'profile' => null,
                'reputation' => ['average' => null, 'count' => 0, 'breakdown' => array_fill(1, 5, 0)],
                'reviews' => new LengthAwarePaginator([], 0, $pageSize),
            ]);
        }

        return view('lawyer.reviews.index', [
            'profile' => $profile,
            'reputation' => $this->reviews->reputation($profile),
            'reviews' => $this->reviews->publicReviews($profile, (int) config('reviews.lawyer_page_size', 10)),
        ]);
    }
}
