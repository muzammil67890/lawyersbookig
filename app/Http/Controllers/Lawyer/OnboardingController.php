<?php

namespace App\Http\Controllers\Lawyer;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lawyer\LawyerOnboardingRequest;
use App\Models\LawyerProfile;
use App\Models\LawyerVerification;
use App\Models\Service;
use App\Models\Specialization;
use App\Models\User;
use App\Notifications\LawyerApplicationSubmitted;
use App\Services\LawyerFileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The Phase 4 lawyer onboarding workflow: a lawyer who registered
 * (Phase 3) with a bare `role = lawyer` user and no profile completes
 * their professional profile, uploads CNIC/face verification images,
 * and their application enters the PENDING admin review queue.
 *
 * Deliberately a one-time flow: once a `LawyerProfile` exists for the
 * user, `create()`/`store()` both redirect to the dashboard instead of
 * allowing a second submission (or an already-approved lawyer editing
 * their way back to PENDING) -- resubmission/editing is not part of
 * this phase.
 */
class OnboardingController extends Controller
{
    public function __construct(protected LawyerFileUploadService $uploads)
    {
    }

    public function create(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user->lawyerProfile) {
            return redirect()->route('lawyer.dashboard');
        }

        return view('lawyer.onboarding', [
            'services' => Service::query()->active()->orderBy('name')->get(),
            'specializations' => Specialization::query()->orderBy('name')->get()->groupBy('service_id'),
        ]);
    }

    public function store(LawyerOnboardingRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->lawyerProfile) {
            return redirect()->route('lawyer.dashboard');
        }

        $validated = $request->validated();

        // Uploads happen before the database transaction: Storage
        // writes are not transactional, so on any later failure we
        // explicitly clean up whatever was already written instead of
        // leaving orphaned files behind.
        $storedPaths = [];

        try {
            $profileImagePath = null;
            if ($request->hasFile('profile_image')) {
                $profileImagePath = $this->uploads->store($request->file('profile_image'), 'lawyers/profile', 'public');
                $storedPaths[] = ['path' => $profileImagePath, 'disk' => 'public'];
            }

            $cnicPath = $this->uploads->store($request->file('cnic_image'), 'lawyers/verification/cnic', 'local');
            $storedPaths[] = ['path' => $cnicPath, 'disk' => 'local'];

            $facePath = $this->uploads->store($request->file('face_image'), 'lawyers/verification/face', 'local');
            $storedPaths[] = ['path' => $facePath, 'disk' => 'local'];

            DB::transaction(function () use ($user, $validated, $profileImagePath, $cnicPath, $facePath) {
                $profile = LawyerProfile::query()->create([
                    'user_id' => $user->id,
                    'display_name' => $user->name,
                    'title' => $validated['title'],
                    'bio' => $validated['bio'],
                    'phone' => $validated['phone'] ?? $user->phone,
                    'city' => $validated['city'],
                    'location' => $validated['location'] ?? null,
                    'years_of_experience' => $validated['years_of_experience'],
                    'consultation_fee' => $validated['consultation_fee'],
                    'education' => $validated['education'] ?? null,
                    'bar_council' => $validated['bar_council'] ?? null,
                    'license_number' => $validated['license_number'] ?? null,
                    'profile_image' => $profileImagePath,
                    // 'is_public'/'is_active' are left at their schema
                    // defaults (both true) -- public *visibility
                    // preference* is a separate concern from
                    // *eligibility*, which LawyerProfile::scopeSearchable()
                    // and isEligible() gate on verification_status too.
                    'verification_status' => VerificationStatus::PENDING->value,
                ]);

                $profile->services()->sync($validated['services']);

                if (! empty($validated['specializations'])) {
                    $profile->specializations()->sync($validated['specializations']);
                }

                LawyerVerification::query()->create([
                    'user_id' => $user->id,
                    'cnic_image_path' => $cnicPath,
                    'face_image_path' => $facePath,
                    'status' => VerificationStatus::PENDING->value,
                    'submitted_at' => now(),
                ]);

                if ($profileImagePath && $user->profile_image === null) {
                    $user->update(['profile_image' => $profileImagePath]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $stored) {
                $this->uploads->delete($stored['path'], $stored['disk']);
            }

            throw $e;
        }

        foreach (User::query()->role('admin')->get() as $admin) {
            $admin->notify(new LawyerApplicationSubmitted($user));
        }

        return redirect()->route('lawyer.dashboard')
            ->with('status', 'Your lawyer application has been submitted. Your verification status is Pending -- an administrator will review your documents.');
    }
}
