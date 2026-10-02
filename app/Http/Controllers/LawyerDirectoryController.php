<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lawyers\LawyerDirectoryRequest;
use App\Models\LawyerProfile;
use App\Models\Service;
use App\Models\Specialization;
use App\Services\ReviewService;
use Illuminate\View\View;

/**
 * The real, database-backed lawyer directory and public lawyer
 * profile (Phase 5 Half 1). This is additive: it does not touch the
 * Phase 1 frontend demo (`pages/legal-connect.blade.php` /
 * `legal-connect.js`), which still runs its own separate, purely
 * illustrative "Find Lawyers"/"Profile" views against localStorage
 * demo data and is out of this phase's scope. `/lawyers` and
 * `/lawyers/{lawyer}` here are the real thing, built on
 * `LawyerProfile::scopeSearchable()`/`isEligible()` from Phase 4.
 *
 * Every query in this controller is built exclusively from
 * `LawyerProfile::query()->searchable()->...` -- there is no second,
 * parallel eligibility check anywhere in this class. A lawyer who is
 * PENDING, REJECTED, or INACTIVE/SUSPENDED simply never appears in
 * any result set or is found by any lookup here.
 */
class LawyerDirectoryController extends Controller
{
    /**
     * Allow-list mapping a public `sort` value to a real column +
     * direction. Nothing from the request is ever passed into
     * `orderBy()` directly -- only a value looked up from this array.
     */
    private const SORTS = [
        'name' => ['display_name', 'asc'],
        'fee' => ['consultation_fee', 'asc'],
        'experience' => ['years_of_experience', 'desc'],
        'recent' => ['updated_at', 'desc'],
    ];

    private const DEFAULT_SORT = 'recent';

    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function index(LawyerDirectoryRequest $request): View
    {
        $filters = $request->validated();
        $sortKey = $filters['sort'] ?? self::DEFAULT_SORT;
        [$sortColumn, $sortDirection] = self::SORTS[$sortKey] ?? self::SORTS[self::DEFAULT_SORT];

        $lawyers = LawyerProfile::query()
            ->searchable()
            ->select([
                'id', 'display_name', 'title', 'bio', 'city',
                'years_of_experience', 'consultation_fee', 'profile_image', 'updated_at',
            ])
            ->with([
                'services:id,name,slug',
                'specializations:id,name,slug',
            ])
            // Phase 9: aggregated as correlated subqueries alongside
            // the main listing query -- one round trip for the whole
            // page of lawyers, never one query per lawyer card.
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->published()])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->published()], 'rating')
            ->when(! empty($filters['q']), fn ($query) => $query->keyword($filters['q']))
            ->when(! empty($filters['service']), fn ($query) => $query->offeringService($filters['service']))
            ->when(! empty($filters['specialization']), fn ($query) => $query->offeringSpecialization($filters['specialization']))
            ->when(! empty($filters['city']), fn ($query) => $query->inCity($filters['city']))
            ->when(isset($filters['min_fee']), fn ($query) => $query->minFee((float) $filters['min_fee']))
            ->when(isset($filters['max_fee']), fn ($query) => $query->maxFee((float) $filters['max_fee']))
            ->when(isset($filters['min_experience']), fn ($query) => $query->minExperience((int) $filters['min_experience']))
            ->orderBy($sortColumn, $sortDirection)
            // Phase 11: deterministic tiebreaker so equal fee/experience/name
            // values never shuffle or duplicate rows across pages.
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return view('lawyers.index', [
            'lawyers' => $lawyers,
            'filters' => $filters,
            'sort' => $sortKey,
            'services' => Service::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'specializations' => Specialization::query()->orderBy('name')->get(['id', 'name', 'slug', 'service_id']),
            'cities' => LawyerProfile::query()->searchable()->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    /**
     * The public profile page. `searchable()->findOrFail()` is the
     * whole access-control story here: a lawyer who is PENDING,
     * REJECTED, SUSPENDED/inactive, or simply doesn't exist all
     * produce the exact same 404 -- nothing distinguishes "not
     * approved" from "no such lawyer" in the response.
     */
    public function show(int $lawyer): View
    {
        // findOrFail() throws ModelNotFoundException, which Laravel's
        // exception handler converts to a plain 404 response -- the
        // same response whether the ID doesn't exist at all or exists
        // but fails scopeSearchable() (pending/rejected/inactive).
        $profile = LawyerProfile::query()
            ->searchable()
            ->with([
                'services:id,name,slug',
                'specializations:id,name,slug',
            ])
            ->findOrFail($lawyer);

        return view('lawyers.show', [
            'lawyer' => $profile,
            'reputation' => $this->reviews->reputation($profile),
            'reviews' => $this->reviews->publicReviews($profile, (int) config('reviews.public_page_size', 10)),
        ]);
    }
}
