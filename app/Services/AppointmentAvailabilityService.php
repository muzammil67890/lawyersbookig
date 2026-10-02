<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\LawyerAvailability;
use App\Models\LawyerProfile;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 6 Part 1: the single reusable place that knows how to read a
 * lawyer's configured availability, turn it into bookable slots for a
 * given date, detect appointment conflicts, and create an appointment
 * request transactionally.
 *
 * Nothing in this class trusts a customer_id, lawyer_profile_id, or
 * service_id taken from the request -- every public method here is
 * called with already-resolved, already-authorized Eloquent models.
 * Callers (controllers, Form Requests) are responsible for resolving
 * "which lawyer" / "which service" / "which customer" through
 * LawyerProfile::searchable(), the lawyer's own services() relation,
 * and auth()->user() respectively -- this service only reasons about
 * time.
 *
 * This is deliberately the one place Phase 6 Part 2 (accept/reject,
 * reschedule, cancellation) should extend rather than duplicate.
 */
class AppointmentAvailabilityService
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('appointments', []);
    }

    public function durationMinutes(): int
    {
        return (int) ($this->config['default_duration_minutes'] ?? 30);
    }

    public function bookingWindowDays(): int
    {
        return (int) ($this->config['booking_window_days'] ?? 60);
    }

    /**
     * The availability windows that apply to a lawyer on a specific
     * calendar date.
     *
     * A `specific_date` row is a one-off override for that exact date:
     * if any exist for the date, they are used exclusively (whether
     * available or not), so a lawyer can both add a one-off extra slot
     * and block out a normally-recurring day. Otherwise, the lawyer's
     * recurring `day_of_week` rows for that weekday apply. Only rows
     * with `is_available = true` are ever returned as bookable
     * windows -- an inactive row never contributes slots.
     *
     * @return Collection<int, LawyerAvailability>
     */
    public function windowsForDate(LawyerProfile $lawyer, Carbon $date): Collection
    {
        $overrides = $lawyer->availabilities()
            ->whereDate('specific_date', $date->toDateString())
            ->get();

        if ($overrides->isNotEmpty()) {
            return $overrides->where('is_available', true)->values();
        }

        // Carbon::dayOfWeek is 0 (Sunday) .. 6 (Saturday), matching the
        // convention documented on the lawyer_availabilities migration
        // and already used by LawyerMatchingService::DAY_NAMES.
        return $lawyer->availabilities()
            ->whereNull('specific_date')
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_available', true)
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Whether the half-open interval [start, end) sits entirely inside
     * at least one of the lawyer's availability windows for that date.
     * Times are "H:i" or "H:i:s" strings, which compare correctly as
     * plain strings in this fixed-width format.
     */
    public function isWithinAvailability(LawyerProfile $lawyer, Carbon $date, string $start, string $end): bool
    {
        return $this->windowsForDate($lawyer, $date)
            ->contains(fn (LawyerAvailability $window) => $start >= $this->timeString($window->start_time)
                && $end <= $this->timeString($window->end_time));
    }

    /**
     * Whether the requested [start, end) interval overlaps any of the
     * lawyer's existing appointments that still occupy the calendar
     * (pending, confirmed or completed -- see
     * AppointmentStatus::slotBlocking()) on that date. Overlap uses the standard half-open interval test
     * (`start < existing.end AND end > existing.start`), which
     * correctly rejects exact matches, partial overlaps, and either
     * interval fully containing the other, while allowing genuinely
     * back-to-back appointments (one ends exactly when the other
     * starts).
     *
     * Runs entirely in SQL -- never loads every appointment into PHP
     * to check overlap.
     */
    public function hasConflict(LawyerProfile $lawyer, Carbon $date, string $start, string $end, ?int $excludeAppointmentId = null): bool
    {
        return $lawyer->appointments()
            ->whereDate('appointment_date', $date->toDateString())
            ->whereIn('status', AppointmentStatus::slotBlockingValues())
            ->when($excludeAppointmentId, fn ($query) => $query->where('id', '!=', $excludeAppointmentId))
            ->where('start_time', '<', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('end_time')->orWhere('end_time', '>', $start);
            })
            ->exists();
    }

    /**
     * Every free, bookable slot of `durationMinutes()` for a lawyer on
     * a given date: inside an availability window, not already
     * conflicting with an existing appointment, and not in the past.
     * Used by the booking slot picker and, with `$excludeAppointmentId`
     * set to the appointment being moved, by the reschedule slot picker
     * (so an appointment never conflicts with itself).
     *
     * @return Collection<int, array{start: string, end: string}>
     */
    public function availableSlots(LawyerProfile $lawyer, Carbon $date, ?int $durationMinutes = null, ?int $excludeAppointmentId = null): Collection
    {
        $duration = $durationMinutes ?? $this->durationMinutes();
        $now = Carbon::now();
        $slots = collect();

        foreach ($this->windowsForDate($lawyer, $date) as $window) {
            $cursor = Carbon::parse($date->toDateString().' '.$this->timeString($window->start_time));
            $windowEnd = Carbon::parse($date->toDateString().' '.$this->timeString($window->end_time));

            while ($cursor->copy()->addMinutes($duration)->lte($windowEnd)) {
                $slotStart = $cursor->copy();
                $slotEnd = $cursor->copy()->addMinutes($duration);

                if ($slotStart->greaterThan($now)
                    && ! $this->hasConflict($lawyer, $date, $slotStart->format('H:i:s'), $slotEnd->format('H:i:s'), $excludeAppointmentId)) {
                    $slots->push([
                        'start' => $slotStart->format('H:i'),
                        'end' => $slotEnd->format('H:i'),
                    ]);
                }

                $cursor->addMinutes($duration);
            }
        }

        return $slots->values();
    }

    /**
     * Create a pending appointment request for an already-authorized,
     * already-eligible customer/lawyer/service combination.
     *
     * Transaction + conflict protection strategy (requirement #20/#21):
     * every check that matters for correctness (availability, conflict)
     * is re-run *inside* the transaction, immediately before the
     * insert -- never trusting a check performed earlier (e.g. for the
     * slot-picker UI) as still valid by the time this runs. The lawyer's
     * profile row is locked first (see lockLawyerCalendar()), so on
     * MySQL a second concurrent transaction for the same lawyer waits
     * until this one commits or rolls back. SQLite (the automated test
     * database) has no row locks, so true concurrent races are only
     * guarded against on MySQL. There is no partial write in any case:
     * the checks and the insert are one atomic transaction.
     *
     * @throws ValidationException if the requested time is outside
     *         availability or conflicts with an existing appointment.
     */
    public function book(
        User $customer,
        LawyerProfile $lawyer,
        ?Service $service,
        Carbon $date,
        string $startTime,
        string $consultationType,
        ?string $reason,
    ): Appointment {
        $duration = $this->durationMinutes();
        $start = Carbon::parse($date->toDateString().' '.$startTime);
        $end = $start->copy()->addMinutes($duration);

        return DB::transaction(function () use ($customer, $lawyer, $service, $date, $start, $end, $consultationType, $reason) {
            $this->lockLawyerCalendar($lawyer->id);

            $this->assertSlotAvailable($lawyer, $date, $start, $end);

            return Appointment::create([
                'reference' => $this->generateReference(),
                'customer_id' => $customer->id,
                'lawyer_profile_id' => $lawyer->id,
                'service_id' => $service?->id,
                'appointment_date' => $date->toDateString(),
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'consultation_type' => $consultationType,
                'status' => AppointmentStatus::PENDING->value,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Serializes every state change that claims time on a lawyer's
     * calendar (booking, confirming, rescheduling) by taking a row lock
     * on that lawyer's profile row. Must be called inside a transaction.
     *
     * A lock on the lawyer's *appointment* rows would not be enough:
     * when the target date has no appointments yet there is no row to
     * lock, so two concurrent transactions could both pass the conflict
     * check. The profile row always exists, so a second transaction for
     * the same lawyer blocks here (MySQL/InnoDB) until the first commits
     * or rolls back, and only then runs its own conflict check.
     * SQLite, used by the automated tests, has no row locks, so this is
     * a no-op there.
     */
    public function lockLawyerCalendar(int $lawyerProfileId): void
    {
        LawyerProfile::query()->whereKey($lawyerProfileId)->lockForUpdate()->first();
    }

    /**
     * The single "may this interval be claimed?" check shared by
     * booking, confirming and rescheduling: it must lie inside the
     * lawyer's availability and must not overlap another live
     * appointment. `$excludeAppointmentId` removes the appointment
     * being moved/confirmed from the conflict query so it never
     * conflicts with itself.
     *
     * Call inside the transaction, after lockLawyerCalendar().
     *
     * @throws ValidationException
     */
    public function assertSlotAvailable(
        LawyerProfile $lawyer,
        Carbon $date,
        Carbon $start,
        Carbon $end,
        ?int $excludeAppointmentId = null,
        string $errorKey = 'start_time',
    ): void {
        if (! $this->isWithinAvailability($lawyer, $date, $start->format('H:i:s'), $end->format('H:i:s'))) {
            throw ValidationException::withMessages([
                $errorKey => 'The selected time is outside this lawyer\'s availability.',
            ]);
        }

        if ($this->hasConflict($lawyer, $date, $start->format('H:i:s'), $end->format('H:i:s'), $excludeAppointmentId)) {
            throw ValidationException::withMessages([
                $errorKey => 'The selected time is no longer available. Please choose another slot.',
            ]);
        }
    }

    /** Normalizes a Carbon-cast time column back to a plain "H:i:s" string. */
    private function timeString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        return (string) $value;
    }

    public function generateReference(): string
    {
        do {
            $reference = 'LC-'.now()->format('Y').'-'.random_int(10000, 99999);
        } while (Appointment::where('reference', $reference)->exists());

        return $reference;
    }
}
