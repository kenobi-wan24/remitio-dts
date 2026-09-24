<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single place where a document moves.
 * Every movement = 1 new history row + the document's status/holder/location
 * updated in the SAME database transaction, so they can never disagree.
 */
class DocumentTracker
{
    /**
     * What can be done next, based on the document's current status.
     *
     * @return array<MovementAction>
     */
    public function availableActions(Document $document): array
    {
        return match ($document->status) {
            DocumentStatus::Released => [MovementAction::Received, MovementAction::Archived],
            DocumentStatus::Archived => [MovementAction::Received],
            default => [
                MovementAction::Forwarded,
                MovementAction::Returned,
                MovementAction::StatusChanged,
                MovementAction::FiledInCourt,
                MovementAction::ReleasedToClient,
                MovementAction::Archived,
            ],
        };
    }

    /**
     * @param  array{to_user_id?: int|string|null, to_status?: string|null, location?: string|null, remarks?: string|null, acted_at?: string|null}  $data
     */
    public function record(Document $document, MovementAction $action, array $data, User $actor): DocumentMovement
    {
        return DB::transaction(function () use ($document, $action, $data, $actor) {
            // Lock the row so two people can't move the same document at the same instant
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);

            $fromStatus = $document->status;
            $fromHolder = $document->current_holder_id;
            $toUser = filled($data['to_user_id'] ?? null) ? (int) $data['to_user_id'] : null;
            $newStatus = filled($data['to_status'] ?? null) ? DocumentStatus::from($data['to_status']) : null;
            $location = filled($data['location'] ?? null) ? $data['location'] : null;
            $keepLocation = $location ?? $document->physical_location;

            [$toStatus, $toHolder, $newLocation] = match ($action) {
                MovementAction::Forwarded => [$newStatus ?? $fromStatus, $toUser, $keepLocation],
                MovementAction::Returned => [$fromStatus, $toUser, $keepLocation],
                MovementAction::StatusChanged => [$newStatus, $fromHolder, $keepLocation],
                MovementAction::FiledInCourt => [DocumentStatus::Filed, $toUser ?? $fromHolder, $keepLocation],
                MovementAction::ReleasedToClient => [DocumentStatus::Released, null, null],
                MovementAction::Archived => [DocumentStatus::Archived, null, $location],
                MovementAction::Received => [DocumentStatus::Received, $toUser, $keepLocation],
            };

            $movement = $document->movements()->create([
                'action' => $action,
                'from_user_id' => $fromHolder,
                'to_user_id' => $toHolder,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'location' => $action === MovementAction::ReleasedToClient ? null : $location,
                'remarks' => $data['remarks'] ?? null,
                'acted_by' => $actor->id,
                'acted_at' => filled($data['acted_at'] ?? null) ? $data['acted_at'] : now(),
            ]);

            $document->update([
                'status' => $toStatus,
                'current_holder_id' => $toHolder,
                'physical_location' => $newLocation,
            ]);

            return $movement;
        });
    }

    /** Flash message after a movement is saved. */
    public function summary(DocumentMovement $movement): string
    {
        $movement->loadMissing('toUser:id,name');
        $to = $movement->toUser?->name ?? 'nobody';

        return match ($movement->action) {
            MovementAction::Forwarded => "Forwarded to {$to}.",
            MovementAction::Returned => "Returned to {$to}.",
            MovementAction::StatusChanged => "Status updated to {$movement->to_status->label()}.",
            MovementAction::FiledInCourt => 'Marked as filed in court / agency.',
            MovementAction::ReleasedToClient => 'Released to client. The document is now out of the office.',
            MovementAction::Archived => 'Document archived.',
            MovementAction::Received => "Received back. Now with {$to}.",
        };
    }
}
