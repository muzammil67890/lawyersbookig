<?php

namespace App\Enums;

/**
 * The three primary account roles in Legal Connect.
 * String-backed so the value matches the existing `users.role` column
 * and the role strings already used by the Phase 1 frontend prototype.
 */
enum UserRole: string
{
    case CUSTOMER = 'customer';
    case LAWYER = 'lawyer';
    case ADMIN = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Customer',
            self::LAWYER => 'Lawyer',
            self::ADMIN => 'Administrator',
        };
    }
}
