<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 11C: the lawyer can undo a wrong step, with a reason; staff can't. */
class CorrectStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_lawyer_can_correct_a_wrong_status_with_a_reason(): void
    {
        $lawyer = User::factory()->admin()->create();
        $document = Document::factory()->create(['status' => DocumentStatus::Signed]);

        $this->actingAs($lawyer)->post(route('documents.movements.store', $document), [
            'action' => 'status_corrected',
            'corrected_status' => 'for_signature',
            'remarks' => 'Marked as signed by mistake; not yet signed',
        ])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(DocumentStatus::ForSignature, $document->status);
        $this->assertSame(MovementAction::StatusCorrected, $document->movements->first()->action);
        $this->assertSame('Marked as signed by mistake; not yet signed', $document->movements->first()->remarks);
    }

    public function test_a_reason_is_required(): void
    {
        $lawyer = User::factory()->admin()->create();
        $document = Document::factory()->create(['status' => DocumentStatus::Signed]);

        $this->actingAs($lawyer)->post(route('documents.movements.store', $document), [
            'action' => 'status_corrected', 'corrected_status' => 'for_signature',
        ])->assertSessionHasErrors('remarks');
    }

    public function test_staff_cannot_correct_a_status(): void
    {
        $staff = User::factory()->create();
        $document = Document::factory()->create(['status' => DocumentStatus::Signed]);

        $this->actingAs($staff)->post(route('documents.movements.store', $document), [
            'action' => 'status_corrected', 'corrected_status' => 'for_signature', 'remarks' => 'x',
        ])->assertSessionHasErrors('action');

        $this->assertSame(DocumentStatus::Signed, $document->fresh()->status);
    }

    public function test_an_archived_document_can_still_be_corrected_by_the_lawyer(): void
    {
        $lawyer = User::factory()->admin()->create();
        $document = Document::factory()->create(['status' => DocumentStatus::Archived]);

        $this->actingAs($lawyer)->post(route('documents.movements.store', $document), [
            'action' => 'status_corrected', 'corrected_status' => 'released', 'remarks' => 'Archived too early',
        ])->assertSessionHasNoErrors();

        $this->assertSame(DocumentStatus::Released, $document->fresh()->status);
        $this->assertNull($document->fresh()->current_holder_id);
    }
}
