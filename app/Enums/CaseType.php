<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CaseType: string
{
    use HasOptions;

    case Civil = 'civil';
    case Criminal = 'criminal';
    case Labor = 'labor';
    case Administrative = 'administrative';
    case SpecialProceeding = 'special_proceeding';
    case Notarial = 'notarial';
    case Consultation = 'consultation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Civil => 'Civil',
            self::Criminal => 'Criminal',
            self::Labor => 'Labor',
            self::Administrative => 'Administrative',
            self::SpecialProceeding => 'Special Proceeding',
            self::Notarial => 'Notarial',
            self::Consultation => 'Consultation',
            self::Other => 'Other',
        };
    }

    /** Case types that normally go through a court or agency (and get a docket number). */
    public function isLitigated(): bool
    {
        return in_array($this, [
            self::Civil, self::Criminal, self::Labor,
            self::Administrative, self::SpecialProceeding,
        ], true);
    }
}
