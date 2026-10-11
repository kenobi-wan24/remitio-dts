<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CivilStatus: string
{
    use HasOptions;

    case Single = 'single';
    case Married = 'married';
    case Widowed = 'widowed';
    case LegallySeparated = 'legally_separated';
    case Annulled = 'annulled';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Married => 'Married',
            self::Widowed => 'Widowed',
            self::LegallySeparated => 'Legally separated',
            self::Annulled => 'Annulled',
        };
    }
}
