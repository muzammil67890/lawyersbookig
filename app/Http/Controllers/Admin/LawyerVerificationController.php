<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectLawyerVerificationRequest;
use App\Models\LawyerVerification;
use App\Notifications\LawyerApplicationApproved;
use App\Notifications\LawyerApplicationRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Phase 4 admin lawyer-verification review workflow. Sits behind the
 * existing `role:admin` route group (Phase 3), plus an explicit
 * LawyerVerificationPolicy `review` check in approve()/reject() so the
 * rule "only an admin may decide this" is enforced twice, independently.
 */
class LawyerVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', LawyerVerification::class);

        $status = $request->query('status', 'pending');
        $validStatuses = array_map(fn (VerificationStatus $s) => $s->value, VerificationStatus::cases());

        if ($status !== 'all' && ! in_array($status, $validStatuses, true)) {
            $status = 'pending';
        }

        $verifications = LawyerVerification::query()
            ->with(['user', 'user.lawyerProfile'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('submitted_at')
            ->paginate(15)
            ->withQueryString();

        // Phase 11: one grouped query instead of 5 separate COUNT queries.
        // Statuses with zero rows simply never appear in the pluck() result,
        // so they still fall back to 0 below -- the tab counts are unchanged.
        $statusCounts = LawyerVerification::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = [
            'pending' => (int) ($statusCounts[VerificationStatus::PENDING->value] ?? 0),
            'approved' => (int) ($statusCounts[VerificationStatus::APPROVED->value] ?? 0),
            'rejected' => (int) ($statusCounts[VerificationStatus::REJECTED->value] ?? 0),
            'suspended' => (int) ($statusCounts[VerificationStatus::SUSPENDED->value] ?? 0),
            'all' => (int) $statusCounts->sum(),
        ];

        return view('admin.lawyer-verifications.index', [
            'verifications' => $verifications,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    public function show(LawyerVerification $lawyerVerification): View
    {
        $this->authorize('view', $lawyerVerification);

        $lawyerVerification->load([
            'user',
            'user.lawyerProfile.services',
            'user.lawyerProfile.specializations',
            'reviewer',
        ]);

        return view('admin.lawyer-verifications.show', [
            'verification' => $lawyerVerification,
        ]);
    }

    public function approve(LawyerVerification $lawyerVerification): RedirectResponse
    {
        $this->authorize('review', $lawyerVerification);

        // Phase 11: the status is re-read under a row lock inside the
        // transaction, so two admins acting on the same application at
        // once cannot both "win" (and both notify the lawyer).
        $changed = DB::transaction(function () use ($lawyerVerification) {
            $locked = LawyerVerification::query()->whereKey($lawyerVerification->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === VerificationStatus::APPROVED) {
                return false;
            }

            $locked->update([
                'status' => VerificationStatus::APPROVED->value,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            // The profile's verification_status is a denormalized copy
            // kept in sync here (see LawyerProfile migration comment)
            // -- only that one column is touched, nothing else about
            // the profile is overwritten.
            $locked->user->lawyerProfile?->update([
                'verification_status' => VerificationStatus::APPROVED->value,
            ]);

            return true;
        });

        if (! $changed) {
            return redirect()->route('admin.lawyer-verifications.show', $lawyerVerification)
                ->with('status', 'This application is already approved.');
        }

        $lawyerVerification->user->notify(new LawyerApplicationApproved());

        return redirect()->route('admin.lawyer-verifications.show', $lawyerVerification)
            ->with('status', 'Application approved. The lawyer is now eligible for future public/search/booking features.');
    }

    public function reject(RejectLawyerVerificationRequest $request, LawyerVerification $lawyerVerification): RedirectResponse
    {
        $this->authorize('review', $lawyerVerification);

        $reason = $request->validated()['rejection_reason'];

        $changed = DB::transaction(function () use ($lawyerVerification, $reason) {
            $locked = LawyerVerification::query()->whereKey($lawyerVerification->getKey())->lockForUpdate()->firstOrFail();

            // Phase 11: re-rejecting an already-rejected application would
            // only overwrite the reviewer/reason and re-notify the lawyer.
            if ($locked->status === VerificationStatus::REJECTED) {
                return false;
            }

            $locked->update([
                'status' => VerificationStatus::REJECTED->value,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $locked->user->lawyerProfile?->update([
                'verification_status' => VerificationStatus::REJECTED->value,
            ]);

            return true;
        });

        if (! $changed) {
            return redirect()->route('admin.lawyer-verifications.show', $lawyerVerification)
                ->with('status', 'This application is already rejected.');
        }

        $lawyerVerification->user->notify(new LawyerApplicationRejected($reason));

        return redirect()->route('admin.lawyer-verifications.show', $lawyerVerification)
            ->with('status', 'Application rejected.');
    }
}
