<?php

namespace App\Http\Controllers\Lawyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lawyer\StoreLawyerAvailabilityRequest;
use App\Http\Requests\Lawyer\UpdateLawyerAvailabilityRequest;
use App\Models\LawyerAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lawyer-facing availability management (Phase 6 Part 1, requirements
 * #7/#8/#10).
 *
 * Every action here scopes to `$request->user()->lawyerProfile`, and
 * every action on a specific record additionally goes through
 * LawyerAvailabilityPolicy via $this->authorize() -- two independent
 * layers, so neither a route-model-binding mistake nor a missing
 * policy check alone could expose another lawyer's schedule. A lawyer
 * with no LawyerProfile yet (has not completed onboarding) is
 * redirected to start onboarding rather than shown an empty page.
 */
class AvailabilityController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $profile = $request->user()->lawyerProfile;

        if (! $profile) {
            return redirect()->route('lawyer.onboarding.create')
                ->with('status', 'Complete your lawyer application before configuring availability.');
        }

        $availabilities = $profile->availabilities()
            ->orderByRaw('day_of_week is null')
            ->orderBy('day_of_week')
            ->orderBy('specific_date')
            ->orderBy('start_time')
            ->get();

        return view('lawyer.availability.index', [
            'profile' => $profile,
            'availabilities' => $availabilities,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', LawyerAvailability::class);

        return view('lawyer.availability.create');
    }

    public function store(StoreLawyerAvailabilityRequest $request): RedirectResponse
    {
        $this->authorize('create', LawyerAvailability::class);

        $request->user()->lawyerProfile->availabilities()->create($request->validatedForCreate());

        return redirect()->route('lawyer.availability.index')
            ->with('status', 'Availability window added.');
    }

    public function edit(Request $request, LawyerAvailability $availability): View
    {
        $this->authorize('update', $availability);

        return view('lawyer.availability.edit', ['availability' => $availability]);
    }

    public function update(UpdateLawyerAvailabilityRequest $request, LawyerAvailability $availability): RedirectResponse
    {
        $this->authorize('update', $availability);

        $availability->update($request->validatedForUpdate());

        return redirect()->route('lawyer.availability.index')
            ->with('status', 'Availability window updated.');
    }

    public function toggle(Request $request, LawyerAvailability $availability): RedirectResponse
    {
        $this->authorize('update', $availability);

        $availability->update(['is_available' => ! $availability->is_available]);

        return redirect()->route('lawyer.availability.index')
            ->with('status', $availability->is_available ? 'Availability window activated.' : 'Availability window deactivated.');
    }

    public function destroy(Request $request, LawyerAvailability $availability): RedirectResponse
    {
        $this->authorize('delete', $availability);

        $availability->delete();

        return redirect()->route('lawyer.availability.index')
            ->with('status', 'Availability window removed.');
    }
}
