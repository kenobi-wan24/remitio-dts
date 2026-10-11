<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for backed enums.
 * options() → ['value' => 'Label'] for <select> dropdowns
 * values()  → ['value1', 'value2'] for validation rules
 */
trait HasOptions
{
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
