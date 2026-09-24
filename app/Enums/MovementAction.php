<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MovementAction: string
{
    use HasOptions;

    case Received = 'received';
    case Forwarded = 'forwarded';
    case Returned = 'returned';
    case StatusChanged = 'status_changed';
    case FiledInCourt = 'filed_in_court';
    case ReleasedToClient = 'released_to_client';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Forwarded => 'Forwarded',
            self::Returned => 'Returned',
            self::StatusChanged => 'Status Updated',
            self::FiledInCourt => 'Filed in Court / Agency',
            self::ReleasedToClient => 'Released to Client',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received => 'blue',
            self::Forwarded => 'indigo',
            self::Returned => 'amber',
            self::StatusChanged => 'gray',
            self::FiledInCourt => 'purple',
            self::ReleasedToClient => 'green',
            self::Archived => 'gray',
        };
    }

    /** NEW (Phase 6): icon name for <x-icon> */
    public function icon(): string
    {
        return match ($this) {
            self::Received => 'arrow-down-tray',
            self::Forwarded => 'paper-airplane',
            self::Returned => 'arrow-uturn-left',
            self::StatusChanged => 'arrow-path',
            self::FiledInCourt => 'building-library',
            self::ReleasedToClient => 'arrow-up-tray',
            self::Archived => 'archive-box',
        };
    }

    /** NEW (Phase 6): one-line explanation shown in the "Update Tracking" form */
    public function description(): string
    {
        return match ($this) {
            self::Received => 'Document came back to the office.',
            self::Forwarded => 'Hand it to another person in the office.',
            self::Returned => 'Send it back to who gave it.',
            self::StatusChanged => 'Change status, holder stays the same.',
            self::FiledInCourt => 'Filed / submitted to a court or agency.',
            self::ReleasedToClient => 'Original handed over to the client.',
            self::Archived => 'Matter done — store it in the archive.',
        };
    }
}
