<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** The "Type of Notarial Act" column of the register book. */
enum NotarialActType: string
{
    use HasOptions;

    case Acknowledgment = 'acknowledgment';
    case AffirmationOrOath = 'affirmation_or_oath';
    case Jurat = 'jurat';
    case CopyCertification = 'copy_certification';

    public function label(): string
    {
        return match ($this) {
            self::Acknowledgment => 'Acknowledgment',
            self::AffirmationOrOath => 'Affirmation or Oath',
            self::Jurat => 'Jurat',
            self::CopyCertification => 'Copy Certification',
        };
    }

    /** For the weekly certification: "(n) instruments were acknowledged and (n) sworn to before me". */
    public function isSworn(): bool
    {
        return in_array($this, [self::Jurat, self::AffirmationOrOath], true);
    }
}
