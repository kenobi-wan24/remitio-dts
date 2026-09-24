<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UserRole: string
{
    use HasOptions;

    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Staff => 'Staff',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'indigo',
            self::Staff => 'gray',
        };
    }
}
