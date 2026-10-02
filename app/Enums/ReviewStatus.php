<?php

namespace App\Enums;

/** Moderation status for a customer's review of a lawyer. */
enum ReviewStatus: string
{
    case PENDING = 'pending';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
}
