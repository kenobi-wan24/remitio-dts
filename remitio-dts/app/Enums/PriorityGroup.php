<?php

namespace App\Enums;

/**
 * Revision 1 (R4) — the groups the action queue is sorted into (prof's critique item 8).
 * Rules (to confirm with the firm):
 *   On Hold              waiting on the client / another office (checked first, whatever the urgency)
 *   Immediate Attention  urgency Critical, or (if deadlines are on) due today or overdue
 *   Due Soon             (if deadlines are on) due within the next 3 days
 *   High Priority        urgency High
 *   Routine              Normal and Low
 * Inside a group: higher urgency → earlier deadline → waiting longest.
 */
enum PriorityGroup: string
{
    case Immediate = 'immediate';
    case DueSoon = 'due_soon';
    case High = 'high';
    case Routine = 'routine';
    case OnHold = 'on_hold';

    public const DUE_SOON_DAYS = 3;

    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Immediate Attention',
            self::DueSoon => 'Due Soon',
            self::High => 'High Priority',
            self::Routine => 'Routine',
            self::OnHold => 'On Hold',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Immediate => 'red',
            self::DueSoon, self::High => 'amber',
            self::Routine => 'gray',
            self::OnHold => 'purple',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Immediate => 'Critical, or due today / overdue',
            self::DueSoon => 'Deadline within '.self::DUE_SOON_DAYS.' days',
            self::High => 'Marked High by a lawyer',
            self::Routine => 'Normal workflow',
            self::OnHold => 'Waiting on the client or another office',
        };
    }
}
