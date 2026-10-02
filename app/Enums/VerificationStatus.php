<?php

namespace App\Enums;

/**
 * Status of a lawyer's identity/document verification review.
 * This is the single source of truth for lawyer approval state;
 * `lawyer_profiles.verification_status` is a denormalized copy of it
 * kept in sync for fast public-search filtering (see LawyerProfile model).
 */
enum VerificationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::SUSPENDED => 'Suspended',
        };
    }
}
