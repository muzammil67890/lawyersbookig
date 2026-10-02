<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lawyers\LawyerMatchRequest;
use App\Http\Resources\LawyerMatchResource;
use App\Models\LawyerProfile;
use App\Models\Service;
use App\Models\Specialization;
use App\Services\LawyerMatchingService;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\MatchResultSet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Thin HTTP wrapper around LawyerMatchingService (Phase 5 Half 2).
 *
 * All matching, eligibility and scoring logic lives in the service;
 * this controller only validates (LawyerMatchRequest), builds the
 * MatchCriteria value object, and renders either a Blade page or JSON
 * depending on what the client asked for.
 *
 * This is the deterministic matching FOUNDATION, not the AI Assistant:
 * there is no chat, no LLM and no legal advice here. The route is
 * public because it returns only what the public directory already
 * shows for approved + active lawyers.
 */
class LawyerMatchController extends Controller
{
    public function index(LawyerMatchRequest $request, LawyerMatchingService $matching): View|AnonymousResourceCollection
    {
        $criteria = MatchCriteria::fromArray($request->validated());

        if (! $criteria->hasCriteria()) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    'criteria' => 'Provide at least one matching criterion: problem, service, specialization, city, min_budget, max_budget, min_experience or available_day.',
                ]);
            }

            return $this->page($request, null);
        }

        $result = $matching->match($criteria);

        if ($request->expectsJson()) {
            return LawyerMatchResource::collection($result->matches())
                ->additional(['meta' => $result->meta()]);
        }

        return $this->page($request, $result);
    }

    private function page(LawyerMatchRequest $request, ?MatchResultSet $result): View
    {
        return view('lawyers.match', [
            'result' => $result,
            'filters' => $request->validated(),
            'services' => Service::query()->active()->orderBy('name')->get(['id', 'name']),
            'specializations' => Specialization::query()->orderBy('name')->get(['id', 'name', 'service_id']),
            'cities' => LawyerProfile::query()->searchable()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
        ]);
    }
}
