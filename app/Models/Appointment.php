<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'customer_id', 'lawyer_profile_id', 'service_id',
        'appointment_date', 'start_time', 'end_time',
        'consultation_type', 'status', 'reason',
        'cancellation_reason', 'rescheduled_from_id',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'status' => AppointmentStatus::class,
            'consultation_type' => ConsultationType::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function lawyerProfile(): BelongsTo
    {
        return $this->belongsTo(LawyerProfile::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'rescheduled_from_id');
    }

    /** The appointment that replaced this one, when this one was rescheduled. */
    public function rescheduledTo(): HasOne
    {
        return $this->hasOne(Appointment::class, 'rescheduled_from_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function scopeStatus($query, AppointmentStatus|string $status)
    {
        $value = $status instanceof AppointmentStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now()->toDateString());
    }

    /**
     * The value of the "upcoming" list filter: appointments still
     * awaiting or holding a slot whose start is in the future, judged
     * in the application timezone.
     */
    public const FILTER_UPCOMING = 'upcoming';

    /** Every value the appointment list filter accepts ("all" is the absence of a filter). */
    public static function filterValues(): array
    {
        return [
            self::FILTER_UPCOMING,
            ...array_map(fn (AppointmentStatus $status) => $status->value, AppointmentStatus::cases()),
        ];
    }

    /**
     * Applies one of {@see filterValues()}; null or an unknown value
     * leaves the query unfiltered ("all").
     */
    public function scopeFilter($query, ?string $filter)
    {
        if ($filter === self::FILTER_UPCOMING) {
            $today = now()->toDateString();
            $time = now()->format('H:i:s');

            return $query
                ->whereIn('status', [AppointmentStatus::PENDING->value, AppointmentStatus::CONFIRMED->value])
                ->where(function ($query) use ($today, $time) {
                    $query->whereDate('appointment_date', '>', $today)
                        ->orWhere(function ($query) use ($today, $time) {
                            $query->whereDate('appointment_date', $today)->where('start_time', '>', $time);
                        });
                });
        }

        $status = $filter !== null ? AppointmentStatus::tryFrom($filter) : null;

        return $status ? $query->where('status', $status->value) : $query;
    }

    /**
     * Wall-clock start in the application timezone. `appointment_date`
     * and `start_time` are stored as plain date / time-of-day values
     * with no offset; they are always interpreted in `app.timezone`,
     * the same zone `now()` uses, so comparisons never mix zones.
     */
    public function startsAt(): Carbon
    {
        return Carbon::parse(
            $this->appointment_date->toDateString().' '.$this->start_time,
            config('app.timezone')
        );
    }

    /** End of the slot; falls back to the configured duration when `end_time` was never stored. */
    public function endsAt(): Carbon
    {
        if ($this->end_time) {
            return Carbon::parse($this->appointment_date->toDateString().' '.$this->end_time, config('app.timezone'));
        }

        return $this->startsAt()->addMinutes((int) config('appointments.default_duration_minutes', 30));
    }

    public function hasStarted(): bool
    {
        return $this->startsAt()->lessThanOrEqualTo(now());
    }
}
