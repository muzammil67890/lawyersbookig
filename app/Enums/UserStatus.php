<?php

namespace App\Enums;

/**
 * Account-level status for any user (customer, lawyer or admin),
 * independent of lawyer-specific verification status.
 */
enum UserStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::INACTIVE => 'Inactive',
        };
    }
}
