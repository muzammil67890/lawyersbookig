<?php

namespace App\Models;

use App\Enums\AiMessageSender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasFactory;

    protected $fillable = ['ai_conversation_id', 'sender', 'body'];

    protected function casts(): array
    {
        return [
            'sender' => AiMessageSender::class,
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
