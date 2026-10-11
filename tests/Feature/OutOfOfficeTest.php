<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revision 1 (R2) — Problem 3: "once a physical copy leaves the office, its whereabouts are not recorded."
 */
class OutOfOfficeTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private User $lawyer;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secretary = User::factory()->create();
        $this->lawyer = User::factory()->admin()->create();
        $this->document = Document::factory()->create([
            'status' => DocumentStatus::Finalized,
            'current_holder_id' => $this->secretary->id,
            'physical_location' => 'Cabinet 3',
        ]);
    }

    private function sendOut(array $overrides = [])
    {
        return $this->actingAs($this->secretary)->post(route('documents.movements.store', $this->document), [
            'action' => 'sent_out',
            'destination' => 'RTC Branch 12, Davao City',
            'purpose' => 'Filing in court / agency',
            'carried_by' => 'Pedro (process server)',
            'expected_back' => today()->addDays(2)->toDateString(),
            ...$overrides,
        ]);
    }

    public function test_sending_out_records_where_it_went_without_changing_the_status(): void
    {
        $this->sendOut()->assertSessionHasNoErrors()->assertSessionHas('success');

        $document = $this->document->fresh();
        $this->assertTrue($document->isOutOfOffice());
        $this->assertSame(DocumentStatus::Finalized, $document->status, 'status unchanged');
        $this->assertSame($this->secretary->id, $document->current_holder_id, 'responsible person unchanged');
        $this->assertSame('RTC Branch 12, Davao City', $document->out_destination);
        $this->assertSame('Cabinet 3', $document->physical_location, 'office location kept for its return');

        $this->assertDatabaseHas('document_movements', [
            'document_id' => $document->id,
            'action' => MovementAction::SentOut->value,
            'location' => 'RTC Branch 12, Davao City',
            'purpose' => 'Filing in court / agency',
            'carried_by' => 'Pedro (process server)',
            'from_status' => 'finalized',
            'to_status' => 'finalized',
        ]);
    }

    public function test_destination_purpose_and_carrier_are_required(): void
    {
        $this->sendOut(['destination' => '', 'purpose' => '', 'carried_by' => ''])
            ->assertSessionHasErrors(['destination', 'purpose', 'carried_by']);

        $this->sendOut(['purpose' => 'Other'])->assertSessionHasErrors('remarks');
        $this->sendOut(['purpose' => 'Picnic'])->assertSessionHasErrors('purpose');
        $this->assertFalse($this->document->fresh()->isOutOfOffice());
    }

    public function test_while_out_the_next_step_waits_until_it_is_back(): void
    {
        $this->sendOut();

        $this->actingAs($this->secretary)->post(route('documents.movements.store', $this->document), ['action' => 'signed', 'signed_by' => $this->lawyer->id])
            ->assertSessionHasErrors('action');
        $this->actingAs($this->secretary)->post(route('documents.movements.store', $this->document), ['action' => 'sent_out'])
            ->assertSessionHasErrors('action');
    }

    public function test_returning_clears_the_out_state_and_logs_where_it_is_kept(): void
    {
        $this->sendOut();

        $this->actingAs($this->secretary)->post(route('documents.movements.store', $this->document), [
            'action' => 'returned_to_office',
            'location' => 'Cabinet 1',
            'carried_by' => 'Pedro (process server)',
            'remarks' => 'With the court\'s received-stamped copy',
        ])->assertSessionHasNoErrors();

        $document = $this->document->fresh();
        $this->assertFalse($document->isOutOfOffice());
        $this->assertNull($document->out_destination);
        $this->assertSame('Cabinet 1', $document->physical_location);
        $this->assertSame(DocumentStatus::Finalized, $document->status);
        $this->assertSame(2, $document->movements()->count());

        // the normal workflow continues
        $this->actingAs($this->secretary)->post(route('documents.movements.store', $this->document), ['action' => 'signed', 'signed_by' => $this->lawyer->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(DocumentStatus::SignedSealed, $document->fresh()->status);
    }

    public function test_out_of_office_documents_are_listed_on_the_dashboard_and_document_list(): void
    {
        $this->sendOut();

        $this->actingAs($this->secretary)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Out of Office (1)')
            ->assertSee('RTC Branch 12, Davao City');

        $this->assertSame(1, $this->actingAs($this->secretary)->get(route('documents.index', ['show' => 'out']))->viewData('documents')->total());

        $this->actingAs($this->secretary)->get(route('documents.show', $this->document))
            ->assertOk()
            ->assertSee('Physical copy out of the office')
            ->assertSee('Pedro (process server)');
    }

    public function test_released_or_archived_documents_cannot_be_sent_out(): void
    {
        $this->document->update(['status' => DocumentStatus::Released, 'current_holder_id' => null]);

        $this->sendOut()->assertSessionHasErrors('action');
    }
}
