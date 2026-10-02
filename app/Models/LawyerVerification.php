<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LawyerVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'cnic_image_path', 'face_image_path', 'status',
        'reviewed_by', 'reviewed_at', 'rejection_reason', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VerificationStatus::class,
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /** The lawyer (user) this verification record belongs to. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The admin who reviewed this verification, if any. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', VerificationStatus::PENDING->value);
    }
}
