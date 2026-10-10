<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Revision 1 (R4) — how soon a document needs attention.
 * Proposed levels (prof's critique item 4) — to be validated with the firm.
 * Only lawyers change it, with a reason, and every change is kept in the document's history.
 */
enum Urgency: string
{
    use HasOptions;

    case Critical = 'critical';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::High => 'High',
            self::Normal => 'Normal',
            self::Low => 'Low',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'red',
            self::High => 'amber',
            self::Normal => 'gray',
            self::Low => 'blue',
        };
    }

    /** The written criteria shown when choosing a level (so it isn't just personal judgment). */
    public function criteria(): string
    {
        return match ($this) {
            self::Critical => 'Needs immediate attention: a serious, time-sensitive matter or an imminent deadline (e.g. due today or tomorrow, hearing tomorrow).',
            self::High => 'Needs prompt action: an approaching deadline or a significant consequence if delayed.',
            self::Normal => 'Follows the standard workflow and the usual processing time.',
            self::Low => 'Can wait until more urgent matters are done, without missing any deadline or obligation.',
        };
    }

    /** Sort order: Critical first. */
    public function rank(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::High => 1,
            self::Normal => 2,
            self::Low => 3,
        };
    }

    /** SQL expression that sorts by urgency (Critical first) — works on MySQL/MariaDB and SQLite. */
    public static function rankSql(string $column = 'urgency'): string
    {
        return "CASE {$column} WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END";
    }
}
