<?php

namespace App\Models;

use App\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's legal matter/case.
 *
 * Named `LegalCase` rather than `Case` because `case` is a reserved
 * word in PHP and cannot be used as a class name. Mapped explicitly
 * back to the `cases` table below.
 */
class LegalCase extends Model
{
    use HasFactory;

    protected $table = 'cases';

    protected $fillable = [
        'case_number', 'customer_id', 'lawyer_profile_id', 'service_id',
        'title', 'description', 'status', 'opened_at', 'closed_at',
        'notes', 'customer_notes', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'opened_at' => 'date',
            'closed_at' => 'date',
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

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class, 'case_id');
    }

    public function scopeStatus($query, CaseStatus|string $status)
    {
        $value = $status instanceof CaseStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /** Phase 7: a customer's own cases -- never another customer's. */
    public function scopeForCustomer($query, User $user)
    {
        return $query->where('customer_id', $user->id);
    }

    /** Phase 7: cases assigned to a given lawyer user -- never an unrelated lawyer's. */
    public function scopeForLawyerUser($query, User $user)
    {
        return $query->whereHas('lawyerProfile', fn ($q) => $q->where('user_id', $user->id));
    }
}
