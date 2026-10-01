<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Workflow v2 (agreed with the firm): 10 statuses, in workflow order.
 * Received → For Drafting → For Review ⇄ Revision Required → Approved → For Signature
 * → Signed → Finalized → Released → Archived
 */
enum DocumentStatus: string
{
    use HasOptions;

    case Received = 'received';
    case ForDrafting = 'for_drafting';
    case ForReview = 'for_review';
    case RevisionRequired = 'revision_required';
    case Approved = 'approved';
    case ForSignature = 'for_signature';
    case Signed = 'signed';
    case Finalized = 'finalized';
    case Released = 'released';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::ForDrafting => 'For Drafting',
            self::ForReview => 'For Review',
            self::RevisionRequired => 'Revision Required',
            self::Approved => 'Approved',
            self::ForSignature => 'For Signature',
            self::Signed => 'Signed',
            self::Finalized => 'Finalized',
            self::Released => 'Released',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received => 'blue',
            self::ForDrafting => 'indigo',
            self::ForReview => 'amber',
            self::RevisionRequired => 'red',
            self::Approved => 'green',
            self::ForSignature => 'amber',
            self::Signed => 'purple',
            self::Finalized => 'indigo',
            self::Released => 'green',
            self::Archived => 'gray',
        };
    }

    /** One-line meaning, shown on the document page. */
    public function meaning(): string
    {
        return match ($this) {
            self::Received => 'New request logged. Drafting has not started.',
            self::ForDrafting => 'The secretary is preparing the document.',
            self::ForReview => 'Waiting for the lawyer to check the draft.',
            self::RevisionRequired => 'The lawyer asked for corrections.',
            self::Approved => 'Content approved. Print it, and record the notarial entry if needed.',
            self::ForSignature => 'Waiting for the lawyer to sign.',
            self::Signed => 'Signed by the lawyer. Ready to be sealed and finalized.',
            self::Finalized => 'Sealed and complete. Ready to release.',
            self::Released => 'Given to the client.',
            self::Archived => 'Matter done. Office copy stored.',
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

    /** Statuses where the document is waiting on the lawyer. */
    public function isWithLawyer(): bool
    {
        return in_array($this, [self::ForReview, self::ForSignature], true);
    }

    public static function lawyerValues(): array
    {
        return [self::ForReview->value, self::ForSignature->value];
    }

    /** Statuses where the secretary has the next step. */
    public static function secretaryValues(): array
    {
        return [self::Received->value, self::ForDrafting->value, self::RevisionRequired->value,
            self::Approved->value, self::Signed->value, self::Finalized->value];
    }

    /** A Notarial Register entry can be added from Approved until (not including) Released. */
    public function allowsNotarialEntry(): bool
    {
        return in_array($this, [self::Approved, self::ForSignature, self::Signed, self::Finalized], true);
    }
}
