<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CaseStatus: string
{
    use HasOptions;

    case Open = 'open';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Active => 'Active',
            self::OnHold => 'On Hold',
            self::Closed => 'Closed',
        };
    }

    /** Color name only — the Blade badge component maps it to Tailwind classes. */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Active => 'green',
            self::OnHold => 'amber',
            self::Closed => 'gray',
        };
    }
}
