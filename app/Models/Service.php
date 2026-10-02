<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'icon', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function specializations(): HasMany
    {
        return $this->hasMany(Specialization::class);
    }

    public function lawyerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(LawyerProfile::class, 'lawyer_service')->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(LegalCase::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
