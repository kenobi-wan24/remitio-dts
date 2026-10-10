<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Workflow v2 actions (the "next step" buttons).
 * Returned, StatusChanged and FiledInCourt are kept ONLY so older history lines still display;
 * they are never offered as buttons. (Court filing is on hold until confirmed with the firm.)
 */
enum MovementAction: string
{
    use HasOptions;

    case Received = 'received';
    case DraftingStarted = 'drafting_started';
    case SubmittedForReview = 'submitted_for_review';
    case Approved = 'approved';
    case RevisionRequired = 'revision_required';
    case Resubmitted = 'resubmitted';
    case SentForSignature = 'sent_for_signature';
    case Signed = 'signed';
    case Finalized = 'finalized';
    case ReleasedToClient = 'released_to_client';
    case Archived = 'archived';
    case Forwarded = 'forwarded';           // "Hand over": holder changes, status doesn't
    case StatusCorrected = 'status_corrected'; // 11C: lawyer fixes a wrong click (reason required)
    case SentOut = 'sent_out';                 // R2: physical copy leaves the office (status unchanged)
    case ReturnedToOffice = 'returned_to_office'; // R2: physical copy is back in the office
    case UrgencyChanged = 'urgency_changed';   // R4: lawyer changed the urgency (reason required)
    case PutOnHold = 'put_on_hold';            // R4: waiting on the client / another office
    case Resumed = 'resumed';                  // R4: no longer on hold

    // Legacy (history only)
    case Returned = 'returned';
    case StatusChanged = 'status_changed';
    case FiledInCourt = 'filed_in_court';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::DraftingStarted => 'Drafting Started',
            self::SubmittedForReview => 'Submitted for Review',
            self::Approved => 'Approved',
            self::RevisionRequired => 'Revision Required',
            self::Resubmitted => 'Resubmitted for Review',
            self::SentForSignature => 'Sent for Signature',
            self::Signed => 'Signed',
            self::Finalized => 'Finalized',
            self::ReleasedToClient => 'Released to Client',
            self::Archived => 'Archived',
            self::Forwarded => 'Handed Over',
            self::StatusCorrected => 'Status Corrected',
            self::SentOut => 'Sent Out of Office',
            self::ReturnedToOffice => 'Returned to Office',
            self::UrgencyChanged => 'Urgency Changed',
            self::PutOnHold => 'Put On Hold',
            self::Resumed => 'Resumed',
            self::Returned => 'Returned',
            self::StatusChanged => 'Status Updated',
            self::FiledInCourt => 'Filed in Court / Agency',
        };
    }

    /** Text on the next-step button. */
    public function buttonLabel(): string
    {
        return match ($this) {
            self::Received => 'Receive back',
            self::DraftingStarted => 'Start drafting',
            self::SubmittedForReview => 'Submit for review',
            self::Approved => 'Approve',
            self::RevisionRequired => 'Revision required',
            self::Resubmitted => 'Resubmit for review',
            self::SentForSignature => 'Send for signature',
            self::Signed => 'Mark as signed',
            self::Finalized => 'Finalize',
            self::ReleasedToClient => 'Release to client',
            self::Archived => 'Archive',
            self::Forwarded => 'Hand over',
            self::StatusCorrected => 'Correct status',
            self::SentOut => 'Send out of office',
            self::ReturnedToOffice => 'Returned to office',
            default => $this->label(),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received, self::DraftingStarted => 'blue',
            self::SubmittedForReview, self::Resubmitted, self::SentForSignature => 'amber',
            self::Approved, self::ReleasedToClient => 'green',
            self::RevisionRequired, self::StatusCorrected => 'red',
            self::Signed => 'purple',
            self::Finalized, self::Forwarded => 'indigo',
            self::SentOut => 'amber',
            self::UrgencyChanged => 'red',
            self::PutOnHold, self::Resumed => 'purple',
            self::ReturnedToOffice => 'blue',
            default => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Received => 'arrow-down-tray',
            self::DraftingStarted => 'document-text',
            self::SubmittedForReview, self::SentForSignature => 'paper-airplane',
            self::Approved => 'check-circle',
            self::RevisionRequired, self::Returned => 'arrow-uturn-left',
            self::Resubmitted, self::StatusChanged => 'arrow-path',
            self::Signed => 'pencil-square',
            self::Finalized => 'lock-closed',
            self::ReleasedToClient => 'arrow-up-tray',
            self::Archived => 'archive-box',
            self::Forwarded => 'arrows-right-left',
            self::FiledInCourt => 'building-library',
            self::StatusCorrected => 'exclamation-triangle',
            self::SentOut => 'truck',
            self::ReturnedToOffice => 'building-office',
            self::UrgencyChanged => 'exclamation-triangle',
            self::PutOnHold => 'clock',
            self::Resumed => 'arrow-path',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Received => 'The document came back to the office.',
            self::DraftingStarted => 'The lawyer gave instructions; drafting begins.',
            self::SubmittedForReview => 'The draft is done; send it to the lawyer to check.',
            self::Approved => 'The content is correct; choose the approved file version.',
            self::RevisionRequired => 'Send it back to the secretary with corrections.',
            self::Resubmitted => 'The corrections are done; upload the new version.',
            self::SentForSignature => 'Printed and given to the lawyer to sign.',
            self::Signed => 'The lawyer has signed the document.',
            self::Finalized => 'Sealed, completed, and the client\'s copy prepared.',
            self::ReleasedToClient => 'Given to the client or their representative.',
            self::Archived => 'The matter is done; store the office copy.',
            self::Forwarded => 'Pass it to another person without changing the status.',
            self::StatusCorrected => 'Undo a wrong step: set the right status and give the reason.',
            self::SentOut => 'The physical copy is leaving the office (e.g. to court or to a client). The status stays the same.',
            self::ReturnedToOffice => 'The physical copy is back in the office.',
            default => '',
        };
    }

    /** R2: steps that only record where the physical copy is (they never change the status). */
    /** R4: priority-only steps (no change of status, holder or location). */
    public function isPriorityStep(): bool
    {
        return in_array($this, [self::UrgencyChanged, self::PutOnHold, self::Resumed], true);
    }

    public function isOutOfOfficeStep(): bool
    {
        return in_array($this, [self::SentOut, self::ReturnedToOffice], true);
    }

    /** R2: common reasons a physical copy leaves the office (the form also allows "Other"). */
    public const OUT_PURPOSES = [
        'Filing in court / agency',
        'Client signature',
        'Signing or notarization outside the office',
        'Delivery to client or third party',
        'Photocopy or scanning outside',
        'Other',
    ];

    /** Only the lawyer (Administrator) may take these steps. */
    public function isLawyerOnly(): bool
    {
        return in_array($this, [self::Approved, self::RevisionRequired, self::StatusCorrected], true);
    }
}
