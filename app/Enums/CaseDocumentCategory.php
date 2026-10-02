<?php

namespace App\Enums;

/**
 * Phase 7: the document-type system referenced by CaseDocument::category.
 * No enum existed for it before this phase (the column was a free
 * string) -- these values were chosen to match exactly what
 * database/factories/CaseDocumentFactory.php and the domain schema
 * tests already use, plus GENERAL as the default catch-all, so
 * existing rows/tests are already valid values of this enum with no
 * data migration required.
 */
enum CaseDocumentCategory: string
{
    case GENERAL = 'general';
    case EVIDENCE = 'evidence';
    case CONTRACT = 'contract';
    case IDENTIFICATION = 'identification';
    case CORRESPONDENCE = 'correspondence';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General',
            self::EVIDENCE => 'Evidence',
            self::CONTRACT => 'Agreement / Contract',
            self::IDENTIFICATION => 'Identification',
            self::CORRESPONDENCE => 'Correspondence',
        };
    }
}
