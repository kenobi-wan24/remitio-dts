<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\DocumentType;
use App\Models\LegalCase;
use App\Models\NotarialEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demo data following Workflow v2:
 * Received → For Drafting → For Review ⇄ Revision Required → Approved → For Signature
 * → Signed → Finalized → Released → Archived (+ Notarial Register entries)
 */
class DemoDataSeeder extends Seeder
{
    private Collection $staff;

    private Collection $lawyers;

    /** Notarizable document types get a register entry once approved. */
    private const NOTARIZABLE = ['Affidavit', 'Deed', 'Power of Attorney', 'Contract / Agreement', 'Certificate'];

    private array $register = []; // series => last doc no.

    public function run(): void
    {
        // NOTE: factory state() closures are re-bound to the factory, so `$this`
        // inside them is the FACTORY, not this seeder. Use local variables there.
        $users = User::active()->get();
        $attorneys = $users->where('role', UserRole::Admin)->values();
        $this->lawyers = $attorneys;
        $this->staff = $users->where('role', UserRole::Staff)->values();
        if ($this->staff->isEmpty()) {
            $this->staff = $users;
        }

        $randomUser = fn () => ['created_by' => $users->random()->id];

        // 1) Clients
        $clients = Client::factory(16)->state($randomUser)->create()
            ->merge(Client::factory(6)->company()->state($randomUser)->create());

        // 2) Cases + documents per client
        foreach ($clients as $client) {
            $cases = LegalCase::factory(fake()->numberBetween(1, 2))
                ->for($client)
                ->state(fn () => [
                    'handling_attorney_id' => $attorneys->random()->id,
                    'created_by' => $users->random()->id,
                ])
                ->create();

            foreach ($cases as $case) {
                Document::factory(fake()->numberBetween(1, 3))
                    ->forCase($case)
                    ->state($randomUser)
                    ->create()
                    ->each(fn (Document $doc) => $this->simulateHistory($doc));
            }
        }

        // 3) A few walk-in documents with no case (e.g. notarial)
        Document::factory(6)
            ->state(fn () => ['client_id' => $clients->random()->id, 'created_by' => $users->random()->id])
            ->create()
            ->each(fn (Document $doc) => $this->simulateHistory($doc));
    }

    /**
     * Walks a document through the Workflow v2 steps to a random stopping point,
     * writing one movement per step (sometimes with a revision loop).
     */
    private function simulateHistory(Document $document): void
    {
        $secretary = $this->staff->random();
        $lawyer = $document->legalCase?->handling_attorney_id
            ? $this->lawyers->firstWhere('id', $document->legalCase->handling_attorney_id) ?? $this->lawyers->random()
            : $this->lawyers->random();

        $at = Carbon::parse($document->date_received)->setTime(fake()->numberBetween(8, 11), fake()->numberBetween(0, 59));
        $status = DocumentStatus::Received;
        $holder = $secretary;

        $this->move($document, MovementAction::Received, null, $secretary, null, $status, 'Client request received and logged.', $secretary, $at);

        // The steps in order: [action, new status, new holder, acted by, remarks]
        $steps = [
            [MovementAction::DraftingStarted, DocumentStatus::ForDrafting, $secretary, $secretary, 'Lawyer\'s instructions noted; drafting started.'],
            [MovementAction::SubmittedForReview, DocumentStatus::ForReview, $lawyer, $secretary, 'Draft ready for review.'],
        ];
        if (fake()->boolean(40)) { // revision loop
            $steps[] = [MovementAction::RevisionRequired, DocumentStatus::RevisionRequired, $secretary, $lawyer, 'Please correct the client\'s middle name and revise paragraph 3.'];
            $steps[] = [MovementAction::Resubmitted, DocumentStatus::ForReview, $lawyer, $secretary, 'Revised per your comments.'];
        }
        $steps = [...$steps,
            [MovementAction::Approved, DocumentStatus::Approved, $secretary, $lawyer, 'Content approved.'],
            [MovementAction::SentForSignature, DocumentStatus::ForSignature, $lawyer, $secretary, 'Printed and given for signature.'],
            [MovementAction::Signed, DocumentStatus::Signed, $secretary, $lawyer, "Signed by {$lawyer->name}."],
            [MovementAction::Finalized, DocumentStatus::Finalized, $secretary, $secretary, 'Sealed; client copy prepared.'],
            [MovementAction::ReleasedToClient, DocumentStatus::Released, null, $secretary, 'Original released.'],
            [MovementAction::Archived, DocumentStatus::Archived, null, $secretary, 'Matter completed.'],
        ];

        $stopAt = fake()->numberBetween(0, count($steps));

        foreach (array_slice($steps, 0, $stopAt) as [$action, $newStatus, $newHolder, $actor, $remarks]) {
            $at = $at->copy()->addDays(fake()->numberBetween(0, 3))->addHours(fake()->numberBetween(1, 5));
            if ($at->isFuture()) {
                break;
            }

            $this->move($document, $action, $holder, $newHolder, $status, $newStatus, $remarks, $actor, $at,
                $action === MovementAction::ReleasedToClient ? $document->client?->display_name : null,
                $action === MovementAction::Archived ? 'Archive Room - Box '.fake()->numberBetween(1, 20) : null);

            // Notarized documents get a register entry right after approval
            if ($action === MovementAction::Approved && in_array($document->documentType?->name, self::NOTARIZABLE, true)) {
                $this->addNotarialEntry($document, $actor, $at->copy()->addMinutes(30));
            }

            $holder = $newHolder;
            $status = $newStatus;
        }

        $document->update([
            'status' => $status,
            'current_holder_id' => $holder?->id,
            'physical_location' => match ($status) {
                DocumentStatus::Released => null,
                DocumentStatus::Archived => 'Archive Room - Box '.fake()->numberBetween(1, 20),
                default => $document->physical_location,
            },
        ]);
    }

    private function move(Document $document, MovementAction $action, ?User $from, ?User $to, ?DocumentStatus $fromStatus,
        DocumentStatus $toStatus, string $remarks, User $actor, Carbon $at, ?string $receivedBy = null, ?string $location = null): void
    {
        DocumentMovement::create([
            'document_id' => $document->id,
            'action' => $action,
            'from_user_id' => $from?->id,
            'to_user_id' => $to?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'location' => $location ?? ($action === MovementAction::Received ? $document->physical_location : null),
            'remarks' => $remarks,
            'received_by' => $receivedBy,
            'acted_by' => $actor->id,
            'acted_at' => $at,
        ]);
    }

    /** Numbered in order per year: Doc. No. 1, 2, 3… · 4 docs per page · 100 docs per book. */
    private function addNotarialEntry(Document $document, User $by, Carbon $at): void
    {
        $series = $at->year;
        $n = $this->register[$series] = ($this->register[$series] ?? 0) + 1;
        $books = ['I', 'II', 'III', 'IV', 'V'];

        $entry = new NotarialEntry([
            'document_id' => $document->id,
            'doc_no' => $n,
            'page_no' => intdiv($n - 1, 4) + 1,
            'book_no' => $books[min(intdiv($n - 1, 100), 4)],
            'series' => $series,
            'recorded_by' => $by->id,
        ]);
        $entry->created_at = $at;
        $entry->updated_at = $at;
        $entry->save();
    }
}
