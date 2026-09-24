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
}
