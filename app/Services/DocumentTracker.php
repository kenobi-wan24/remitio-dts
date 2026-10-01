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
        return array_values(array_filter(
            self::FLOW[$document->status->value] ?? [],
            fn (MovementAction $a) => ! $a->isLawyerOnly() || ($user?->isAdmin() ?? false),
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

        if (! $document->status->isFinal()) {
            $actions[] = MovementAction::Forwarded;
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
            $toStatus = $action === MovementAction::Forwarded ? $fromStatus : self::RESULT[$action->value];
            $location = filled($data['location'] ?? null) ? $data['location'] : null;
            $remarks = filled($data['remarks'] ?? null) ? $data['remarks'] : null;

            // Who holds it next — set from the status, so nobody has to pick a person on each step
            $toHolder = match ($action) {
                MovementAction::Forwarded => (int) $data['to_user_id'],
                MovementAction::SubmittedForReview, MovementAction::Resubmitted, MovementAction::SentForSignature => (int) $data['lawyer_id'],
                MovementAction::Approved, MovementAction::RevisionRequired, MovementAction::Signed => $this->sentToLawyerBy($document) ?? $fromHolder,
                MovementAction::ReleasedToClient, MovementAction::Archived => null,
                MovementAction::Received => $actor->id,
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
                'acted_by' => $actor->id,
                'acted_at' => filled($data['acted_at'] ?? null) ? $data['acted_at'] : now(),
            ]);

            $document->update([
                'status' => $toStatus,
                'current_holder_id' => $toHolder,
                'physical_location' => match ($action) {
                    MovementAction::ReleasedToClient => null,
                    default => $location ?? $document->physical_location,
                },
            ]);

            ActivityLogger::log('moved', $document, "{$action->label()}: document {$document->tracking_code}", [
                'from_status' => $fromStatus->value,
                'to_status' => $toStatus->value,
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
