<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LawyerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'display_name', 'title', 'bio', 'phone',
        'city', 'location', 'address',
        'years_of_experience', 'consultation_fee', 'starting_fee',
        'experience_summary', 'education', 'bar_council', 'license_number',
        'profile_image', 'is_public', 'is_active', 'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'consultation_fee' => 'decimal:2',
            'starting_fee' => 'decimal:2',
            'years_of_experience' => 'integer',
            'verification_status' => VerificationStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verification(): HasOne
    {
        return $this->hasOne(LawyerVerification::class, 'user_id', 'user_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'lawyer_service')->withTimestamps();
    }

    public function specializations(): BelongsToMany
    {
        return $this->belongsToMany(Specialization::class, 'lawyer_specialization')->withTimestamps();
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(LawyerAvailability::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(LegalCase::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * The single definition of "publicly eligible": approved, active,
     * opted into public visibility, AND owned by a user account whose
     * own status is `active`. The directory (Phase 5 Half 1) and the
     * lawyer-matching engine (Phase 5 Half 2) are both built on this
     * scope -- neither re-derives eligibility anywhere else.
     *
     * The owning-account check (added in Phase 5 Half 2) closes a gap:
     * an admin-suspended/inactive *user account* must never surface
     * publicly even if the profile row itself still says approved.
     * It is an EXISTS subquery on the users primary key, so it adds no
     * join and no extra round trip.
     */
    public function scopeSearchable($query)
    {
        return $query->where('is_active', true)
            ->where('is_public', true)
            ->where('verification_status', VerificationStatus::APPROVED->value)
            ->whereHas('user', fn ($user) => $user->where('status', UserStatus::ACTIVE->value));
    }

    public function scopeInCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function scopeMaxFee($query, float $fee)
    {
        return $query->where('consultation_fee', '<=', $fee);
    }

    public function scopeMinFee($query, float $fee)
    {
        return $query->where('consultation_fee', '>=', $fee);
    }

    public function scopeMinExperience($query, int $years)
    {
        return $query->where('years_of_experience', '>=', $years);
    }

    /**
     * Keyword search across the fields a public visitor would expect a
     * lawyer search to match: name, professional title, bio, city, and
     * the names of the services/specializations they offer. Always
     * combined with scopeSearchable() by the caller -- this scope does
     * not itself restrict to eligible lawyers.
     */
    public function scopeKeyword($query, string $keyword)
    {
        // Phase 11: escape LIKE wildcards so a literal "%" or "_" typed by
        // a visitor is matched as text instead of widening the search.
        // "!" is the escape character because `ESCAPE '!'` behaves the
        // same on MySQL and SQLite (a backslash does not).
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword).'%';

        return $query->where(function ($query) use ($like) {
            $query->whereRaw("display_name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("title LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("bio LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("city LIKE ? ESCAPE '!'", [$like])
                ->orWhereHas('services', fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$like]))
                ->orWhereHas('specializations', fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$like]));
        });
    }

    public function scopeOfferingService($query, string $serviceSlug)
    {
        return $query->whereHas('services', fn ($q) => $q->where('slug', $serviceSlug));
    }

    public function scopeOfferingSpecialization($query, string $specializationSlug)
    {
        return $query->whereHas('specializations', fn ($q) => $q->where('slug', $specializationSlug));
    }

    /**
     * The single reusable definition of "publicly/bookably eligible"
     * (Phase 4 requirement #18) -- mirrors scopeSearchable() but for a
     * single already-loaded model instance, e.g. in Blade views. Every
     * future phase should call this (or scopeSearchable()) rather than
     * re-deriving "approved + active [+ public]" logic elsewhere.
     */
    public function isEligible(): bool
    {
        return $this->is_active
            && $this->is_public
            && $this->verification_status === VerificationStatus::APPROVED
            && $this->user?->status === UserStatus::ACTIVE;
    }
}
