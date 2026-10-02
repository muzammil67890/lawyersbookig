<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', 'profile_image', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /*
    |--------------------------------------------------------------------
    | Lawyer-only relationships
    |--------------------------------------------------------------------
    | Meaningful only when role === UserRole::LAWYER, but left
    | unrestricted here: the future authorization layer enforces who
    | may have a profile/verification, not the relationship definition.
    */

    public function lawyerProfile(): HasOne
    {
        return $this->hasOne(LawyerProfile::class);
    }

    public function lawyerVerification(): HasOne
    {
        return $this->hasOne(LawyerVerification::class);
    }

    /** Verifications this user has reviewed, when the user is an admin. */
    public function reviewedVerifications(): HasMany
    {
        return $this->hasMany(LawyerVerification::class, 'reviewed_by');
    }

    /*
    |--------------------------------------------------------------------
    | Customer-side relationships
    |--------------------------------------------------------------------
    */

    public function appointmentsAsCustomer(): HasMany
    {
        return $this->hasMany(Appointment::class, 'customer_id');
    }

    public function casesAsCustomer(): HasMany
    {
        return $this->hasMany(LegalCase::class, 'customer_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class, 'customer_id');
    }

    /*
    |--------------------------------------------------------------------
    | Relationships available regardless of role
    |--------------------------------------------------------------------
    */

    /** Appointments booked with this user, when the user is a lawyer. */
    public function appointmentsAsLawyer(): HasManyThrough
    {
        return $this->hasManyThrough(
            Appointment::class,
            LawyerProfile::class,
            'user_id',
            'lawyer_profile_id'
        );
    }

    /** Cases assigned to this user, when the user is a lawyer. */
    public function casesAsLawyer(): HasManyThrough
    {
        return $this->hasManyThrough(
            LegalCase::class,
            LawyerProfile::class,
            'user_id',
            'lawyer_profile_id'
        );
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(CaseDocument::class, 'uploaded_by');
    }

    public function scopeRole($query, UserRole|string $role)
    {
        $value = $role instanceof UserRole ? $role->value : $role;

        return $query->where('role', $value);
    }

    public function scopeActive($query)
    {
        return $query->where('status', UserStatus::ACTIVE->value);
    }

    public function isLawyer(): bool
    {
        return $this->role === UserRole::LAWYER;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }
}
