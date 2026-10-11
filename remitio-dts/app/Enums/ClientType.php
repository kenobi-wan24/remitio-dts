<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ClientType: string
{
    use HasOptions;

    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Company => 'Company / Organization',
        };
    }
}
