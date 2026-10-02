<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'lawyer_profile_id', 'status', 'last_message_at'];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
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

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The single most recent message, for conversation-list previews.
     * Using latestOfMany() (a correlated subquery) rather than
     * `->messages()->latest()->first()` per row keeps the conversation
     * list at one query no matter how many conversations it shows
     * (requirement #27/#28: no N+1).
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * The single definition of "is this user a party to this private
     * conversation" (Phase 8). ConversationPolicy and MessagingService
     * both call this rather than re-deriving customer/lawyer ownership
     * separately, mirroring the isEligible()-style single source of
     * truth already used by LawyerProfile, AppointmentPolicy and
     * LegalCasePolicy elsewhere in the app. Deliberately excludes
     * admins: private messages are private to the two parties only
     * (requirement #15).
     */
    public function isParticipant(User $user): bool
    {
        if ($user->isCustomer()) {
            return $user->id === $this->customer_id;
        }

        if ($user->isLawyer()) {
            return $user->lawyerProfile !== null && $user->lawyerProfile->id === $this->lawyer_profile_id;
        }

        return false;
    }

    /** The user on the other side of this conversation from $user's perspective. */
    public function otherPartyFor(User $user): ?User
    {
        if ($user->id === $this->customer_id) {
            return $this->lawyerProfile?->user;
        }

        return $this->customer;
    }
}
