<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UserRole: string
{
    use HasOptions;

    case Admin = 'admin';     // Lawyer/Owner + administrator tools
    case Lawyer = 'lawyer';   // R3: lawyer duties only (review, approve, sign, notarize)
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator (Lawyer/Owner)',
            self::Lawyer => 'Lawyer',
            self::Staff => 'Staff',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'indigo',
            self::Lawyer => 'blue',
            self::Staff => 'gray',
        };
    }

    /** What the role is for (shown on the user form and the permissions page). */
    public function description(): string
    {
        return match ($this) {
            self::Admin => 'The Lawyer/Owner. All lawyer duties, plus users, document types, backups, activity log and removed entries.',
            self::Lawyer => 'Reviews, approves, signs and notarizes. Sees confidential matters assigned to them. No administrator tools.',
            self::Staff => 'Records clients, cases and documents, drafts, and moves documents through the workflow.',
        };
    }

    /** Roles that act as a lawyer (Administrator = the Lawyer/Owner). */
    public static function lawyerValues(): array
    {
        return [self::Admin->value, self::Lawyer->value];
    }
}
