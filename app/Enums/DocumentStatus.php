<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Case order = the normal workflow order of a document in the office.
 */
enum DocumentStatus: string
{
    use HasOptions;

    case Received = 'received';
    case InReview = 'in_review';
    case ForSignature = 'for_signature';
    case Filed = 'filed';
    case Released = 'released';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::InReview => 'In Review',
            self::ForSignature => 'For Signature',
            self::Filed => 'Filed',
            self::Released => 'Released',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received => 'blue',
            self::InReview => 'indigo',
            self::ForSignature => 'amber',
            self::Filed => 'purple',
            self::Released => 'green',
            self::Archived => 'gray',
        };
    }

    /** Final = the document has left the office's active workflow. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Released, self::Archived], true);
    }

    public static function finalValues(): array
    {
        return [self::Released->value, self::Archived->value];
    }
}
