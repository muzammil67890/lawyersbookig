<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
    case CLOSED = 'closed';
}
