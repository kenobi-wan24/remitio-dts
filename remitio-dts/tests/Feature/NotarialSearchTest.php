<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\NotarialEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revision 1 (R2) — professor's critique item 6: find Notarial Register entries by name.
 */
class NotarialSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create();
    }

    private function entry(string $principal, int $docNo, array $doc = [], array $extra = []): NotarialEntry
    {
        // fixed, unrelated client name: random demo names (Dela Cruz, Reyes…) would make searches flaky
        $client = Client::factory()->create(['first_name' => 'Zed', 'last_name' => 'Client'.$docNo, 'company_name' => null]);
        $document = Document::factory()->create([
            'status' => DocumentStatus::Approved, 'client_id' => $client->id, 'title' => 'Instrument '.$docNo, ...$doc,
        ]);

        return NotarialEntry::create([
            'document_id' => $document->id,
            'notary_id' => User::factory()->admin()->create()->id,
            'notarized_on' => today()->toDateString(),
            'act_type' => 'acknowledgment',
            'doc_no' => $docNo, 'page_no' => 1, 'book_no' => 'I', 'series' => 2026,
            'principals' => [['name' => $principal, 'address' => 'Davao City', 'evidence' => null]],
            'witnesses' => [['name' => 'Maria Santos', 'address' => 'Matina']],
            'recorded_by' => $this->staff->id,
            ...$extra,
        ]);
    }

    private function find(array $query): array
    {
        return $this->actingAs($this->staff)->get(route('notarial.index', $query))
            ->assertOk()
            ->viewData('entries')
            ->pluck('doc_no')
            ->sort()
            ->values()
            ->all();
    }

    public function test_entries_are_found_by_part_of_a_principals_name_ignoring_case(): void
    {
        $this->entry('Juan Dela Cruz', 1);
        $this->entry('Pedro Penduko', 2);

        $this->assertSame([1], $this->find(['name' => 'dela cruz']));
        $this->assertSame([1], $this->find(['name' => 'JUAN']));
        $this->assertSame([2], $this->find(['name' => 'Pend']));
    }

    public function test_names_with_n_tilde_are_found(): void
    {
        $this->entry('Rosa Peñaranda', 3);
        $this->entry('Ana Reyes', 4);

        $this->assertSame([3], $this->find(['name' => 'Peñaranda']));
    }

    public function test_entries_are_found_by_witness_or_the_documents_client(): void
    {
        $client = Client::factory()->create(['first_name' => 'Lorna', 'last_name' => 'Villanueva']);
        $this->entry('Someone Else', 5, ['client_id' => $client->id], ['witnesses' => []]);
        $this->entry('Another Person', 6, [], ['witnesses' => [['name' => 'Carlo Mendoza', 'address' => '']]]);

        $this->assertSame([5], $this->find(['name' => 'Villanueva']));
        $this->assertSame([6], $this->find(['name' => 'mendoza']));
    }

    public function test_entries_can_be_filtered_by_instrument_type_and_notarized_date(): void
    {
        $affidavit = DocumentType::create(['name' => 'Affidavit']);
        $deed = DocumentType::create(['name' => 'Deed of Sale']);
        $this->entry('A', 7, ['document_type_id' => $affidavit->id], ['notarized_on' => '2026-03-10']);
        $this->entry('B', 8, ['document_type_id' => $deed->id], ['notarized_on' => '2026-06-15']);

        $this->assertSame([8], $this->find(['type' => $deed->id]));
        $this->assertSame([7], $this->find(['from' => '2026-03-01', 'to' => '2026-03-31']));
        $this->assertSame([7, 8], $this->find(['from' => '2026-01-01']));
    }
}
