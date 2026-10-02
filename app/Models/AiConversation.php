<?php

namespace App\Models;

use App\Enums\AiConversationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's session with the future AI matching assistant. Stores
 * the structured requirements the AI gathers; no matching engine lives
 * here -- that is later-phase application logic built on top of this
 * table.
 */
class AiConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'service_id', 'status',
        'location', 'budget_min', 'budget_max', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiConversationStatus::class,
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }
}
