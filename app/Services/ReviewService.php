<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Models\Appointment;
use App\Models\LawyerProfile;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Phase 9: the only code in the application that creates, edits, or
 * moderates a review. Mirrors the shape of AppointmentStatusService --
 * controllers never touch the `reviews` table directly; they map a
 * route to one of the methods here, which:
 *
 *  1. authorizes the acting user through ReviewPolicy (the single
 *     source of truth for "is this appointment reviewable by this
 *     customer right now" -- reused unchanged by the controller and
 *     the Blade "Leave a review" button);
 *  2. opens a transaction and locks the appointment row before
 *     re-checking eligibility, so two near-simultaneous submissions
 *     for the same appointment can never both succeed (the database's
 *     own unique constraint on (customer_id, appointment_id) is the
 *     final backstop if this were ever bypassed);
 *  3. derives customer_id and lawyer_profile_id exclusively from the
 *     appointment row, never from request input -- StoreReviewRequest
 *     and UpdateReviewRequest do not even define those fields.
 *
 * Reviews publish immediately on creation. Unlike lawyer verification
 * or case documents, a review's authenticity is already fully
 * established by the hard eligibility rule it passed through to be
 * created at all (a genuine completed appointment, one review per
 * appointment, owner-only editing) -- there is nothing left for a
 * human moderator to pre-approve. The `status` column (pending /
 * published / rejected) that has sat on this table since Phase 2
 * exists for the opposite direction: after-the-fact admin moderation
 * of abusive or spammy content (see moderate()/restore()), not a
 * pre-publication gate. `Review::scopePublished()` is what every
 * public-facing query in this phase reads through.
 */
class ReviewService
{
    /**
     * Creates a published review for a completed appointment.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws ValidationException
     */
    public function create(Appointment $appointment, User $actor, int $rating, ?string $comment): Review
    {
        Gate::forUser($actor)->authorize('create', [Review::class, $appointment]);

        $review = DB::transaction(function () use ($appointment, $actor, $rating, $comment) {
            // Re-read and lock the appointment row inside the
            // transaction so a race between two requests for the same
            // appointment (e.g. a double form submission) can never
            // create two reviews -- the second one fails the re-check
            // below rather than racing the unique constraint.
            $locked = Appointment::query()
                ->with('lawyerProfile')
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! Gate::forUser($actor)->allows('create', [Review::class, $locked])) {
                throw ValidationException::withMessages([
                    'appointment' => 'This appointment is no longer eligible for a review.',
                ]);
            }

            return Review::create([
                'customer_id' => $locked->customer_id,
                'lawyer_profile_id' => $locked->lawyer_profile_id,
                'appointment_id' => $locked->id,
                'rating' => $rating,
                'comment' => $comment,
                'status' => ReviewStatus::PUBLISHED,
            ]);
        });

        $this->notifyLawyer($review);

        return $review;
    }

    /**
     * Updates a review's rating/comment. Never touches customer_id,
     * lawyer_profile_id, or appointment_id -- those relationships are
     * immutable once a review exists.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(Review $review, User $actor, int $rating, ?string $comment): Review
    {
        Gate::forUser($actor)->authorize('update', $review);

        $review->rating = $rating;
        $review->comment = $comment;
        $review->save();

        return $review;
    }

    /**
     * Withdraws a review entirely. Owner-initiated only (see
     * ReviewPolicy::delete()) -- an admin who wants a review off the
     * public profile uses moderate() instead, which preserves the
     * record rather than erasing it.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function delete(Review $review, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $review);

        $review->delete();
    }

    /**
     * Admin moderation: hides an abusive/spam review from public view
     * without deleting it, preserving the audit trail.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function reject(Review $review, User $actor): Review
    {
        Gate::forUser($actor)->authorize('moderate', $review);

        $review->status = ReviewStatus::REJECTED;
        $review->save();

        return $review;
    }

    /** Admin moderation: reverses a previous rejection. */
    public function restore(Review $review, User $actor): Review
    {
        Gate::forUser($actor)->authorize('moderate', $review);

        $review->status = ReviewStatus::PUBLISHED;
        $review->save();

        return $review;
    }

    /**
     * Notification is best-effort and sent only after the review has
     * committed -- a delivery failure is reported but never undoes or
     * hides the review the customer just submitted, matching
     * AppointmentStatusService::notify()'s pattern exactly.
     */
    private function notifyLawyer(Review $review): void
    {
        $recipient = $review->lawyerProfile?->user;

        if ($recipient === null) {
            return;
        }

        try {
            $recipient->notify(new ReviewNotification($review));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Aggregate reputation for a lawyer profile: average rating (null
     * when there are no published reviews yet), total published review
     * count, and a 1-5 star breakdown. Computed with two lightweight
     * aggregate queries (no eager-loading every review row into
     * memory), so this is safe to call from the public profile page,
     * the lawyer dashboard, and the directory card without an N+1 or a
     * large in-memory collection.
     *
     * @return array{average: float|null, count: int, breakdown: array<int, int>}
     */
    public function reputation(LawyerProfile $lawyer): array
    {
        $totals = $lawyer->reviews()
            ->published()
            ->selectRaw('COUNT(*) as count, AVG(rating) as average')
            ->first();

        $count = (int) ($totals->count ?? 0);
        $average = $count > 0 ? round((float) $totals->average, 1) : null;

        $breakdown = array_fill(1, 5, 0);

        $lawyer->reviews()
            ->published()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->get()
            ->each(function ($row) use (&$breakdown) {
                $breakdown[(int) $row->rating] = (int) $row->count;
            });

        return ['average' => $average, 'count' => $count, 'breakdown' => $breakdown];
    }

    /**
     * Paginated, newest-first published reviews for a public lawyer
     * profile. The customer relation is eager-loaded with only the
     * public display fields selected -- no email/phone ever leaves
     * this query, closing the "private data leakage" requirement at
     * the source rather than relying on the view to omit fields.
     */
    public function publicReviews(LawyerProfile $lawyer, int $perPage): LengthAwarePaginator
    {
        return $lawyer->reviews()
            ->published()
            ->with('customer:id,name')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
