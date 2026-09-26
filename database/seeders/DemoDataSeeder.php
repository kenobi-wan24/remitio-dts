<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Realistic-looking demo data: clients → cases → documents → movement history.
 */
class DemoDataSeeder extends Seeder
{
    private Collection $users;

    public function run(): void
    {
        // NOTE: factory state() closures are re-bound to the factory, so `$this`
        // inside them is the FACTORY, not this seeder. Use local variables there.
        $users = $this->users = User::active()->get();
        $attorneys = $users->where('role', UserRole::Admin)->values();

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
     * Walks a document forward through the workflow from "received" to a
     * random stopping point, writing one movement per step.
     */
    private function simulateHistory(Document $document): void
    {
        $flow = DocumentStatus::cases();                       // in workflow order
        $stopAt = fake()->randomElement([0, 1, 1, 2, 2, 3, 3, 4, 5]);

        $at = $document->date_received->copy()->setTime(fake()->numberBetween(8, 11), fake()->numberBetween(0, 59));
        $holder = $this->users->random();

        DocumentMovement::create([
            'document_id' => $document->id,
            'action' => MovementAction::Received,
            'to_user_id' => $holder->id,
            'to_status' => DocumentStatus::Received,
            'location' => $document->physical_location,
            'remarks' => 'Document received and logged at the front desk.',
            'acted_by' => $holder->id,
            'acted_at' => $at,
        ]);

        $currentStatus = DocumentStatus::Received;

        for ($i = 1; $i <= $stopAt; $i++) {
            $nextStatus = $flow[$i];
            $at = $at->copy()->addDays(fake()->numberBetween(1, 6))->setTime(fake()->numberBetween(8, 16), fake()->numberBetween(0, 59));

            if ($at->isFuture()) {
                break;
            }

            $nextHolder = $nextStatus->isFinal() ? null : $this->users->random();

            $action = match ($nextStatus) {
                DocumentStatus::Filed => MovementAction::FiledInCourt,
                DocumentStatus::Released => MovementAction::ReleasedToClient,
                DocumentStatus::Archived => MovementAction::Archived,
                default => $nextHolder?->id !== $holder?->id ? MovementAction::Forwarded : MovementAction::StatusChanged,
            };

            DocumentMovement::create([
                'document_id' => $document->id,
                'action' => $action,
                'from_user_id' => $holder?->id,
                'to_user_id' => $nextHolder?->id,
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'location' => $nextStatus === DocumentStatus::Archived ? 'Archive Room' : null,
                'remarks' => $this->remarkFor($nextStatus),
                'acted_by' => ($holder ?? $this->users->random())->id,
                'acted_at' => $at,
            ]);

            $holder = $nextHolder;
            $currentStatus = $nextStatus;
        }

        $document->update([
            'status' => $currentStatus,
            'current_holder_id' => $holder?->id,
            'physical_location' => $currentStatus === DocumentStatus::Archived
                ? 'Archive Room - Box '.fake()->numberBetween(1, 20)
                : ($currentStatus === DocumentStatus::Released ? null : $document->physical_location),
        ]);
    }

    private function remarkFor(DocumentStatus $status): string
    {
        return match ($status) {
            DocumentStatus::InReview => 'Forwarded to attorney for review.',
            DocumentStatus::ForSignature => 'Reviewed. Prepared for signature.',
            DocumentStatus::Filed => 'Filed and received-stamped.',
            DocumentStatus::Released => 'Original released to client. Photocopy retained.',
            DocumentStatus::Archived => 'Matter completed. Moved to archive.',
            default => '',
        };
    }
}