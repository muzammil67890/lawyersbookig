<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LawyerAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'lawyer_profile_id', 'day_of_week', 'specific_date',
        'start_time', 'end_time', 'is_recurring', 'is_available',
    ];

    protected function casts(): array
    {
        return [
            'specific_date' => 'date',
            'is_recurring' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    public function lawyerProfile(): BelongsTo
    {
        return $this->belongsTo(LawyerProfile::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }
}
