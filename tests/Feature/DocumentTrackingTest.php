<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Workflow v2: Received → For Drafting → For Review ⇄ Revision Required → Approved
 * → For Signature → Signed → Finalized → Released → Archived
 */
class DocumentTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF";

    private User $secretary;

    private User $lawyer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->secretary = User::factory()->create(['name' => 'Jessa Secretary']);
        $this->lawyer = User::factory()->admin()->create(['name' => 'Atty. Reyes']);
    }

    private function pdf(string $name = 'draft.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::PDF);
    }

    private function recordDocument(): Document
    {
        $this->actingAs($this->secretary)->post(route('documents.store'), [
            'title' => 'Special Power of Attorney',
            'document_type_id' => DocumentType::create(['name' => 'Power of Attorney'])->id,
            'client_id' => Client::factory()->create()->id,
            'date_received' => today()->toDateString(),
            'current_holder_id' => $this->secretary->id,
        ]);

        return Document::latest('id')->firstOrFail();
    }

    private function step(User $as, Document $document, array $data)
    {
        return $this->actingAs($as)->post(route('documents.movements.store', $document), $data);
    }

    public function test_a_new_request_is_received_with_a_tracking_code(): void
    {
        $document = $this->recordDocument();

        $this->assertMatchesRegularExpression('/^DOC-\d{4}-\d{5}$/', $document->tracking_code);
        $this->assertSame(DocumentStatus::Received, $document->status);
        $this->assertSame(MovementAction::Received, $document->movements->first()->action);
    }

    public function test_the_full_flow_from_request_to_archive(): void
    {
        $d = $this->recordDocument();
        $s = $this->secretary;
        $l = $this->lawyer;

        $this->step($s, $d, ['action' => 'drafting_started', 'remarks' => 'Prepare an SPA'])->assertSessionHasNoErrors();
        $this->assertSame(DocumentStatus::ForDrafting, $d->fresh()->status);

        $this->step($s, $d, ['action' => 'submitted_for_review', 'lawyer_id' => $l->id, 'file' => $this->pdf()])->assertSessionHasNoErrors();
        $this->assertSame(DocumentStatus::ForReview, $d->fresh()->status);
        $this->assertSame($l->id, $d->fresh()->current_holder_id, 'the lawyer holds it while reviewing');

        $this->step($l, $d, ['action' => 'revision_required', 'remarks' => 'Fix the middle name'])->assertSessionHasNoErrors();
        $this->assertSame(DocumentStatus::RevisionRequired, $d->fresh()->status);
        $this->assertSame($s->id, $d->fresh()->current_holder_id, 'it goes back to whoever sent it');

        $this->step($s, $d, ['action' => 'resubmitted', 'lawyer_id' => $l->id, 'file' => $this->pdf('draft-v2.pdf')])->assertSessionHasNoErrors();
        $v2 = DocumentAttachment::latest('id')->firstOrFail();
        $this->assertSame(2, $v2->version);

        $this->step($l, $d, ['action' => 'approved', 'attachment_id' => $v2->id])->assertSessionHasNoErrors();
        $this->assertSame(DocumentStatus::Approved, $d->fresh()->status);
        $this->assertTrue($v2->fresh()->is_final, 'approval marks the chosen version FINAL');

        $this->step($s, $d, ['action' => 'sent_for_signature', 'lawyer_id' => $l->id])->assertSessionHasNoErrors();
        $this->step($l, $d, ['action' => 'signed', 'signed_by' => $l->id])->assertSessionHasNoErrors();
        $this->assertSame(DocumentStatus::Signed, $d->fresh()->status);

        $this->step($s, $d, ['action' => 'finalized'])->assertSessionHasNoErrors();
        $this->step($s, $d, ['action' => 'released_to_client', 'received_by' => 'Juan Dela Cruz'])->assertSessionHasNoErrors();
        $this->assertSame(DocumentStatus::Released, $d->fresh()->status);
        $this->assertNull($d->fresh()->current_holder_id);
        $this->assertDatabaseHas('document_movements', ['document_id' => $d->id, 'received_by' => 'Juan Dela Cruz']);

        $this->step($s, $d, ['action' => 'archived', 'location' => 'Archive Room - Box 4'])->assertSessionHasNoErrors();
        $this->assertSame(DocumentStatus::Archived, $d->fresh()->status);
        $this->assertCount(11, $d->fresh()->movements);
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $d = $this->recordDocument();

        $this->step($this->secretary, $d, ['action' => 'released_to_client', 'received_by' => 'Someone'])
            ->assertSessionHasErrors('action');

        $this->assertSame(DocumentStatus::Received, $d->fresh()->status);
    }

    public function test_only_the_lawyer_can_approve_or_request_revision(): void
    {
        $d = $this->recordDocument();
        $this->step($this->secretary, $d, ['action' => 'drafting_started']);
        $this->step($this->secretary, $d, ['action' => 'submitted_for_review', 'lawyer_id' => $this->lawyer->id, 'file' => $this->pdf()]);
        $file = DocumentAttachment::firstOrFail();

        $this->step($this->secretary, $d, ['action' => 'approved', 'attachment_id' => $file->id])->assertSessionHasErrors('action');
        $this->step($this->secretary, $d, ['action' => 'revision_required', 'remarks' => 'x'])->assertSessionHasErrors('action');
        $this->assertSame(DocumentStatus::ForReview, $d->fresh()->status);
    }

    public function test_required_inputs_for_each_step(): void
    {
        $d = $this->recordDocument();
        $this->step($this->secretary, $d, ['action' => 'drafting_started']);

        // a draft file is required when nothing is attached yet
        $this->step($this->secretary, $d, ['action' => 'submitted_for_review', 'lawyer_id' => $this->lawyer->id])
            ->assertSessionHasErrors('file');

        $this->step($this->secretary, $d, ['action' => 'submitted_for_review', 'lawyer_id' => $this->lawyer->id, 'file' => $this->pdf()]);

        // revision needs a comment
        $this->step($this->lawyer, $d, ['action' => 'revision_required'])->assertSessionHasErrors('remarks');

        // approval needs the approved version
        $this->step($this->lawyer, $d, ['action' => 'approved'])->assertSessionHasErrors('attachment_id');
    }

    public function test_hand_over_changes_the_holder_but_not_the_status(): void
    {
        $d = $this->recordDocument();
        $clerk = User::factory()->create();

        $this->step($this->secretary, $d, ['action' => 'forwarded', 'to_user_id' => $clerk->id])->assertSessionHasNoErrors();

        $this->assertSame($clerk->id, $d->fresh()->current_holder_id);
        $this->assertSame(DocumentStatus::Received, $d->fresh()->status);

        $this->step($clerk, $d, ['action' => 'forwarded', 'to_user_id' => $clerk->id])->assertSessionHasErrors('to_user_id');
    }

    public function test_movements_cannot_be_edited_or_deleted(): void
    {
        $d = $this->recordDocument();
        $url = route('documents.movements.store', $d); // the only movements route (POST = add)

        $this->actingAs($this->secretary)->put($url)->assertStatus(405);
        $this->actingAs($this->secretary)->delete($url)->assertStatus(405);
        $this->assertCount(1, $d->fresh()->movements);
    }
}
