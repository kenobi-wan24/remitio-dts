<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Workflow v3 (Revision 2, Phase 1): 9 statuses, in workflow order.
 * Request In → Draft → Review ⇄ Revision → Approved → Finalized → Signature & Sealed → Released → Archived
 *
 * Internal values are kept stable (received, for_drafting, for_review, revision_required)
 * so older records and history keep working; only the labels changed.
 * "Signature & Sealed" (signed_sealed) replaces the old For Signature + Signed.
 */
enum DocumentStatus: string
{
    use HasOptions;

    case Received = 'received';                 // label: Request In
    case ForDrafting = 'for_drafting';          // label: Draft
    case ForReview = 'for_review';              // label: Review
    case RevisionRequired = 'revision_required'; // label: Revision
    case Approved = 'approved';
    case Finalized = 'finalized';
    case SignedSealed = 'signed_sealed';
    case Released = 'released';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Request In',
            self::ForDrafting => 'Draft',
            self::ForReview => 'Review',
            self::RevisionRequired => 'Revision',
            self::Approved => 'Approved',
            self::Finalized => 'Finalized',
            self::SignedSealed => 'Signature & Sealed',
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
            self::Finalized => 'indigo',
            self::SignedSealed => 'purple',
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
            self::Approved => 'Content approved. Finalize it, and record the notarial entry if needed.',
            self::Finalized => 'Content is final. Waiting for the lawyer\'s signature and seal.',
            self::SignedSealed => 'Signed by the lawyer and sealed. Ready to release.',
            self::Released => 'Given to the client.',
            self::Archived => 'Matter done. Office copy stored.',
        };
    }

    /**
     * What the person holding the document has to do next.
     * Used to group the dashboard's "My Action Queue" (one group per action).
     */
    public function pendingAction(): string
    {
        return match ($this) {
            self::Received => 'Awaiting Drafting',
            self::ForDrafting => 'Drafting in Progress',
            self::ForReview => 'Awaiting Review / Approval',
            self::RevisionRequired => 'Revisions to Make',
            self::Approved => 'To Finalize',
            self::Finalized => 'Awaiting Signature & Seal',
            self::SignedSealed => 'Ready for Release',
            self::Released, self::Archived => 'Completed',
        };
    }

    /** In-process statuses, in workflow order (everything except Released and Archived). */
    public static function inProcessCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => ! $s->isFinal()));
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

    /** Statuses where the document is waiting on the lawyer (to review / approve it). */
    public function isWithLawyer(): bool
    {
        return in_array($this, [self::ForReview], true);
    }

    public static function lawyerValues(): array
    {
        return [self::ForReview->value];
    }

    /** Statuses where the secretary has the next step. */
    public static function secretaryValues(): array
    {
        return [self::Received->value, self::ForDrafting->value, self::RevisionRequired->value,
            self::Approved->value, self::Finalized->value, self::SignedSealed->value];
    }

    /** A Notarial Register entry can be added from Approved until (not including) Released. */
    public function allowsNotarialEntry(): bool
    {
        return in_array($this, [self::Approved, self::Finalized, self::SignedSealed], true);
    }
}
