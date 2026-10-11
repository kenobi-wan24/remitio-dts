<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Enums\UserRole;
use App\Enums\NotarialActType;
use App\Models\Client;
use App\Models\ClientId;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\DocumentType;
use App\Models\LegalCase;
use App\Models\NotarialEntry;
use App\Models\User;
use Database\Factories\Support\DemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demo data following Workflow v3:
 * Request In → Draft → Review ⇄ Revision → Approved → Finalized
 * → Signature & Sealed → Released → Archived (+ Notarial Register entries)
 */
class DemoDataSeeder extends Seeder
{
    private Collection $staff;

    private Collection $lawyers;

    /** Notarizable document types get a register entry once approved. */
    private const NOTARIZABLE = ['Affidavit', 'Deed', 'Power of Attorney', 'Contract / Agreement', 'Certificate'];

    private array $register = []; // "notary-series" => last doc no.

    private Collection $clients;

    public function run(): void
    {
        // NOTE: factory state() closures are re-bound to the factory, so `$this`
        // inside them is the FACTORY, not this seeder. Use local variables there.
        $users = User::active()->get();
        $attorneys = $users->filter(fn (User $u) => $u->isLawyer())->values();
        $this->lawyers = $attorneys;
        $this->staff = $users->where('role', UserRole::Staff)->values();
        if ($this->staff->isEmpty()) {
            $this->staff = $users;
        }

        $randomUser = fn () => ['created_by' => $users->random()->id];

        // 1) Clients
        $clients = Client::factory(16)->state($randomUser)->create()
            ->merge(Client::factory(6)->company()->state($randomUser)->create());
        $this->clients = $clients;

        // 11C: most clients have a valid ID on file (no scans in demo data)
        $idTypes = ["Driver's License", 'PhilSys National ID', 'Passport', 'UMID', 'PRC ID', 'Postal ID'];
        foreach ($clients as $client) {
            if (fake()->boolean(85)) {
                ClientId::create([
                    'client_id' => $client->id,
                    'id_type' => fake()->randomElement($idTypes),
                    'id_number' => strtoupper(fake()->bothify('?##-##-######')),
                    'created_by' => $users->random()->id,
                ]);
            }
        }

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

        // 4) R2: a few physical copies currently outside the office
        $this->sendSomeOut();

        // 5) R4: some urgent documents and two waiting on the client
        $open = Document::open()->inRandomOrder()->take(9)->get();
        $open->take(2)->each->update(['urgency' => 'critical']);
        $open->slice(2, 4)->each->update(['urgency' => 'high']);
        $open->slice(6, 1)->each->update(['urgency' => 'low']);
        $open->slice(7, 2)->each->update(['on_hold_since' => now()->subDays(fake()->numberBetween(2, 9)), 'on_hold_reason' => "Waiting for the client's supporting documents"]);
        LegalCase::query()->inRandomOrder()->take(2)->update(['urgency' => 'high']);

        // 6) R3: a confidential case (all its documents follow) and two confidential documents
        LegalCase::query()->whereHas('documents')->inRandomOrder()->first()?->update(['is_confidential' => true]);
        Document::query()->whereNull('legal_case_id')->inRandomOrder()->take(2)->update(['is_confidential' => true]);
    }

    /** R2 demo data: 3 in-process documents brought to court / a client, one past its expected return. */
    private function sendSomeOut(): void
    {
        $trips = [
            ['RTC Branch 12, Davao City', 'Filing in court / agency', 'Rodel (process server)', 2, 1],
            ["Client's office, J.P. Laurel Ave.", 'Client signature', 'Jessa (secretary)', 1, 2],
            ['Office of the City Prosecutor, Davao City', 'Delivery to client or third party', 'Rodel (process server)', 4, -1],
        ];

        $documents = Document::open()
            ->whereIn('status', ['approved', 'finalized', 'signed_sealed', 'for_drafting'])
            ->inRandomOrder()
            ->take(count($trips))
            ->get();

        foreach ($documents as $i => $document) {
            [$destination, $purpose, $carrier, $daysAgo, $expectedIn] = $trips[$i];
            $at = now()->subDays($daysAgo)->setTime(9, 30);
            $lastAt = $document->movements()->max('acted_at');
            if ($lastAt && $at->lt($lastAt)) {
                $at = \Illuminate\Support\Carbon::parse($lastAt)->addHour();
            }
            if ($at->isFuture()) {
                continue;
            }

            DocumentMovement::create([
                'document_id' => $document->id,
                'action' => MovementAction::SentOut,
                'from_user_id' => $document->current_holder_id,
                'to_user_id' => $document->current_holder_id,
                'from_status' => $document->status,
                'to_status' => $document->status,
                'location' => $destination,
                'purpose' => $purpose,
                'carried_by' => $carrier,
                'acted_by' => $document->current_holder_id ?? $this->staff->random()->id,
                'acted_at' => $at,
            ]);

            $document->update([
                'out_since' => $at,
                'out_destination' => $destination,
                'out_purpose' => $purpose,
                'out_carried_by' => $carrier,
                'out_expected_back' => today()->addDays($expectedIn),
            ]);
        }
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

        // 11C: agreements and deeds get two parties (the requesting client + another client)
        if (($document->documentType?->min_parties ?? 1) >= 2) {
            $other = $this->clients->where('id', '!=', $document->client_id)->random();
            $document->parties()->sync([
                $document->client_id => ['role' => 'First Party', 'position' => 0],
                $other->id => ['role' => 'Second Party', 'position' => 1],
            ]);
        }

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
            [MovementAction::Finalized, DocumentStatus::Finalized, $secretary, $secretary, 'Content final; printed for signature and seal.'],
            [MovementAction::Signed, DocumentStatus::SignedSealed, $secretary, $secretary, "Signed by {$lawyer->name}; sealed; client copy prepared."],
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
                $this->addNotarialEntry($document, $lawyer, $actor, $at->copy()->addMinutes(30));
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

    /**
     * A register entry like the book's: numbered in order per notary and year
     * (4 docs per page · 100 docs per book), principals copied from the parties with their valid IDs.
     */
    private function addNotarialEntry(Document $document, User $notary, User $by, Carbon $at): void
    {
        $key = "{$notary->id}-{$at->year}";
        $n = $this->register[$key] = ($this->register[$key] ?? 0) + 1;
        $books = ['I', 'II', 'III', 'IV', 'V'];

        $people = $document->parties()->with('validIds')->get();
        if ($people->isEmpty()) {
            $people = collect([$document->client()->with('validIds')->first()])->filter();
        }

        $actType = match ($document->documentType?->name) {
            'Affidavit', 'Certificate' => NotarialActType::Jurat,
            default => NotarialActType::Acknowledgment,
        };

        $witnesses = $actType === NotarialActType::Acknowledgment && fake()->boolean(60)
            ? collect(range(1, 2))->map(fn () => [
                'name' => DemoData::personName(),
                'address' => fake()->randomElement(DemoData::BARANGAYS).', Davao City',
            ])->all()
            : [];

        $entry = new NotarialEntry([
            'document_id' => $document->id,
            'notary_id' => $notary->id,
            'doc_no' => $n,
            'page_no' => intdiv($n - 1, 4) + 1,
            'book_no' => $books[min(intdiv($n - 1, 100), 4)],
            'series' => $at->year,
            'notarized_on' => $at->toDateString(),
            'act_type' => $actType,
            'principals' => $people->map(fn (Client $c) => [
                'name' => $c->display_name,
                'address' => (string) $c->address,
                'evidence' => (string) $c->validIds->first()?->label,
            ])->values()->all(),
            'witnesses' => $witnesses,
            'recorded_by' => $by->id,
        ]);
        $entry->created_at = $at;
        $entry->updated_at = $at;
        $entry->save();
    }
}
