<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specialization extends Model
{
    use HasFactory;

    protected $fillable = ['service_id', 'name', 'slug'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function lawyerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(LawyerProfile::class, 'lawyer_specialization')->withTimestamps();
    }
}
