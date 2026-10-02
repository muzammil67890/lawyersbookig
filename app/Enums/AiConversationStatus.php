<?php

namespace App\Enums;

enum AiConversationStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case ABANDONED = 'abandoned';
}
