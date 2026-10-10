<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentMovement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Workflow v2 — the single place where a document moves.
 *
 *   Received → For Drafting → For Review ⇄ Revision Required → Approved
 *   → For Signature → Signed → Finalized → Released → Archived
 *
 * Every movement = 1 history row + the document's status/holder/location updated in the
 * SAME transaction (row-locked), so they can never disagree.
 */
class DocumentTracker
{
    /** Next-step buttons for each status (the main flow). */
    private const FLOW = [
        'received' => [MovementAction::DraftingStarted],
        'for_drafting' => [MovementAction::SubmittedForReview],
        'for_review' => [MovementAction::Approved, MovementAction::RevisionRequired],
        'revision_required' => [MovementAction::Resubmitted],
        'approved' => [MovementAction::SentForSignature],
        'for_signature' => [MovementAction::Signed],
        'signed' => [MovementAction::Finalized],
        'finalized' => [MovementAction::ReleasedToClient],
        'released' => [MovementAction::Archived, MovementAction::Received],
        'archived' => [],
    ];

    /** R2: document columns when the physical copy is in the office. */
    private const IN_OFFICE = [
        'out_since' => null,
        'out_destination' => null,
        'out_purpose' => null,
        'out_carried_by' => null,
        'out_expected_back' => null,
    ];

    /** Where each action leaves the document. (Hand over keeps the current status.) */
    private const RESULT = [
        'received' => DocumentStatus::Received,
        'drafting_started' => DocumentStatus::ForDrafting,
        'submitted_for_review' => DocumentStatus::ForReview,
        'approved' => DocumentStatus::Approved,
        'revision_required' => DocumentStatus::RevisionRequired,
        'resubmitted' => DocumentStatus::ForReview,
        'sent_for_signature' => DocumentStatus::ForSignature,
        'signed' => DocumentStatus::Signed,
        'finalized' => DocumentStatus::Finalized,
        'released_to_client' => DocumentStatus::Released,
        'archived' => DocumentStatus::Archived,
    ];

    /**
     * Main next-step actions the user may take now (lawyer-only steps hidden from staff).
     *
     * @return array<MovementAction>
     */
    public function nextSteps(Document $document, ?User $user = null): array
    {
        // R2: while the physical copy is out of the office, it must be logged back in first
        if ($document->isOutOfOffice()) {
            return [];
        }

        return array_values(array_filter(
            self::FLOW[$document->status->value] ?? [],
            fn (MovementAction $a) => ! $a->isLawyerOnly() || ($user?->isLawyer() ?? false),
        ));
    }

    /**
     * Everything the user may do now: the next steps, plus "Hand over" while the document is in the office.
     *
     * @return array<MovementAction>
     */
    public function availableActions(Document $document, ?User $user = null): array
    {
        $actions = $this->nextSteps($document, $user);

        if ($document->isOutOfOffice()) {
            // R2: only "Returned to office" (or the lawyer's correction) while it is out
            $actions[] = MovementAction::ReturnedToOffice;
        } elseif (! $document->status->isFinal()) {
            $actions[] = MovementAction::Forwarded;
            $actions[] = MovementAction::SentOut;
        }

        // 11C: the lawyer can correct a wrong step at any status (reason required)
        if ($user?->isLawyer()) {
            $actions[] = MovementAction::StatusCorrected;
        }

        return $actions;
    }

    /** Which lawyer a document goes to by default: case attorney → last reviewing lawyer → first active admin. */
    public function defaultLawyerId(Document $document): ?int
    {
        return $document->legalCase?->handling_attorney_id
            ?? $document->movements()->whereIn('to_status', DocumentStatus::lawyerValues())->value('to_user_id')
            ?? User::attorneys()->active()->orderBy('id')->value('id');
    }

    /**
     * @param  array{lawyer_id?: mixed, to_user_id?: mixed, attachment_id?: mixed, signed_by?: mixed,
     *               received_by?: ?string, location?: ?string, remarks?: ?string, version_notes?: ?string, acted_at?: ?string}  $data
     */
    public function record(Document $document, MovementAction $action, array $data, User $actor, ?UploadedFile $file = null): DocumentMovement
    {
        return DB::transaction(function () use ($document, $action, $data, $actor, $file) {
            // Lock the row so two people can't move the same document at the same instant
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);

            $fromStatus = $document->status;
            $fromHolder = $document->current_holder_id;
            $toStatus = match ($action) {
                MovementAction::Forwarded, MovementAction::SentOut, MovementAction::ReturnedToOffice => $fromStatus,
                MovementAction::StatusCorrected => DocumentStatus::from($data['corrected_status']),
                default => self::RESULT[$action->value],
            };
            $location = match ($action) {
                MovementAction::SentOut => $data['destination'],   // R2: where it went
                default => filled($data['location'] ?? null) ? $data['location'] : null,
            };
            $remarks = filled($data['remarks'] ?? null) ? $data['remarks'] : null;

            // Who holds it next — set from the status, so nobody has to pick a person on each step
            $toHolder = match ($action) {
                MovementAction::Forwarded => (int) $data['to_user_id'],
                MovementAction::SubmittedForReview, MovementAction::Resubmitted, MovementAction::SentForSignature => (int) $data['lawyer_id'],
                MovementAction::Approved, MovementAction::RevisionRequired, MovementAction::Signed => $this->sentToLawyerBy($document) ?? $fromHolder,
                MovementAction::ReleasedToClient, MovementAction::Archived => null,
                MovementAction::Received => $actor->id,
                // R2: the responsible person doesn't change while the copy is out
                MovementAction::SentOut, MovementAction::ReturnedToOffice => $fromHolder,
                MovementAction::StatusCorrected => $toStatus->isFinal()
                    ? null
                    : (filled($data['to_user_id'] ?? null) ? (int) $data['to_user_id'] : ($fromHolder ?? $actor->id)),
                default => $fromHolder ?? $actor->id,
            };

            // Files: a draft or a corrected version becomes the next file version
            $attachments = app(AttachmentService::class);
            if ($file && $action === MovementAction::SubmittedForReview) {
                $attachments->storeFiles($document, [$file], $actor, $this->latestFile($document), $data['version_notes'] ?? null);
            }
            if ($file && $action === MovementAction::Resubmitted) {
                $attachments->storeFiles($document, [$file], $actor, $this->latestFile($document), $data['version_notes'] ?? 'Revised per the lawyer\'s comments');
            }

            // Approval marks the chosen version as FINAL
            if ($action === MovementAction::Approved) {
                $approved = DocumentAttachment::where('document_id', $document->id)->findOrFail($data['attachment_id']);
                $attachments->markFinal($approved);
                $remarks = trim("Approved version: {$approved->original_name} (v{$approved->version}). ".($remarks ?? ''));
            }

            if ($action === MovementAction::Signed && filled($data['signed_by'] ?? null)) {
                $signer = User::find($data['signed_by'])?->name;
                $remarks = trim("Signed by {$signer}. ".($remarks ?? ''));
            }

            $movement = $document->movements()->create([
                'action' => $action,
                'from_user_id' => $fromHolder,
                'to_user_id' => $toHolder,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'location' => $location,
                'remarks' => $remarks,
                'received_by' => $action === MovementAction::ReleasedToClient ? ($data['received_by'] ?? null) : null,
                'purpose' => $action === MovementAction::SentOut ? $data['purpose'] : null,
                'carried_by' => $action->isOutOfOfficeStep() && filled($data['carried_by'] ?? null) ? $data['carried_by'] : null,
                'acted_by' => $actor->id,
                'acted_at' => filled($data['acted_at'] ?? null) ? $data['acted_at'] : now(),
            ]);

            $document->update([
                'status' => $toStatus,
                'current_holder_id' => $toHolder,
                'physical_location' => match (true) {
                    $action === MovementAction::ReleasedToClient,
                    $action === MovementAction::StatusCorrected && $toStatus === DocumentStatus::Released => null,
                    $action === MovementAction::SentOut => $document->physical_location, // office spot kept for its return
                    default => $location ?? $document->physical_location,
                },
                // R2: current out-of-office state (cleared when it comes back)
                ...match ($action) {
                    MovementAction::SentOut => [
                        'out_since' => $movement->acted_at,
                        'out_destination' => $data['destination'],
                        'out_purpose' => $data['purpose'],
                        'out_carried_by' => $data['carried_by'] ?? null,
                        'out_expected_back' => filled($data['expected_back'] ?? null) ? $data['expected_back'] : null,
                    ],
                    MovementAction::ReturnedToOffice => self::IN_OFFICE,
                    default => [],
                },
            ]);

            ActivityLogger::log('moved', $document, "{$action->label()}: document {$document->tracking_code}", [
                'from_status' => $fromStatus->value,
                'to_status' => $toStatus->value,
            ], $actor);

            return $movement;
        });
    }

    /**
     * R4 — urgency and On Hold. Recorded in the tracking history like any other step,
     * but the status, holder and location never change.
     *
     * @param  array{urgency?: string, reason?: string}  $data
     */
    public function changePriority(Document $document, MovementAction $action, array $data, User $actor): DocumentMovement
    {
        return DB::transaction(function () use ($document, $action, $data, $actor) {
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            $reason = trim((string) ($data['reason'] ?? ''));

            [$changes, $remarks] = match ($action) {
                MovementAction::UrgencyChanged => [
                    ['urgency' => $data['urgency']],
                    "Urgency: {$document->urgency->label()} → ".\App\Enums\Urgency::from($data['urgency'])->label().". Reason: {$reason}",
                ],
                MovementAction::PutOnHold => [
                    ['on_hold_since' => now(), 'on_hold_reason' => $reason],
                    "On hold: {$reason}",
                ],
                MovementAction::Resumed => [
                    ['on_hold_since' => null, 'on_hold_reason' => null],
                    trim('Resumed after being on hold since '.$document->on_hold_since?->format('M d, Y').'. '.$reason),
                ],
            };

            $movement = $document->movements()->create([
                'action' => $action,
                'from_user_id' => $document->current_holder_id,
                'to_user_id' => $document->current_holder_id,
                'from_status' => $document->status,
                'to_status' => $document->status,
                'remarks' => $remarks,
                'acted_by' => $actor->id,
                'acted_at' => now(),
            ]);

            $document->update($changes);

            ActivityLogger::log('moved', $document, "{$action->label()}: document {$document->tracking_code}", [
                'remarks' => $remarks,
            ], $actor);

            return $movement;
        });
    }

    /** Flash message after a movement is saved. */
    public function summary(DocumentMovement $movement): string
    {
        $movement->loadMissing('toUser:id,name');
        $to = $movement->toUser?->name ?? 'nobody';

        return match ($movement->action) {
            MovementAction::DraftingStarted => 'Drafting started.',
            MovementAction::SubmittedForReview, MovementAction::Resubmitted => "Sent to {$to} for review.",
            MovementAction::Approved => 'Approved. The approved version is now marked FINAL.',
            MovementAction::RevisionRequired => "Returned to {$to} for revision.",
            MovementAction::SentForSignature => "Sent to {$to} for signature.",
            MovementAction::Signed => "Marked as signed. Back with {$to} for finalizing.",
            MovementAction::Finalized => 'Finalized. Ready to release.',
            MovementAction::ReleasedToClient => 'Released to client. The document is now out of the office.',
            MovementAction::Archived => 'Document archived.',
            MovementAction::Received => "Received back. Now with {$to}.",
            MovementAction::Forwarded => "Handed over to {$to}.",
            MovementAction::StatusCorrected => "Status corrected to {$movement->to_status->label()}.",
            MovementAction::SentOut => "Logged as out of office: {$movement->location}.",
            MovementAction::ReturnedToOffice => 'Logged as back in the office.',
            default => 'Saved.',
        };
    }

    /** The person who most recently sent the document to the lawyer (gets it back after review/signing). */
    private function sentToLawyerBy(Document $document): ?int
    {
        return $document->movements()->whereIn('to_status', DocumentStatus::lawyerValues())->value('acted_by');
    }

    /** Newest file on the document — new drafts become its next version. */
    private function latestFile(Document $document): ?DocumentAttachment
    {
        return DocumentAttachment::where('document_id', $document->id)->latest('id')->first();
    }
}
