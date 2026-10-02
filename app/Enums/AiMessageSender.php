<?php

namespace App\Enums;

/** Who authored a given message inside an AI matching conversation. */
enum AiMessageSender: string
{
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case SYSTEM = 'system';
}
