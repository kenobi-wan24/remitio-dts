<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentMovement;
use App\Models\DocumentType;
use App\Models\LegalCase;
use App\Models\NotarialEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Revision 1 (R3) — professor's critique items 2 and 7:
 * three roles, confidential records, and access enforced by the backend (not just hidden menus).
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $lawyer;

    private User $staff;      // NOT assigned to the confidential document

    private User $assigned;   // holds the confidential document

    private Document $secret;

    private Document $ordinary;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = User::factory()->admin()->create();
        $this->lawyer = User::factory()->lawyer()->create();
        $this->staff = User::factory()->create();
        $this->assigned = User::factory()->create();

        $this->secret = Document::factory()->create([
            'title' => 'Confidential Compromise Agreement',
            'is_confidential' => true,
            'status' => DocumentStatus::ForDrafting,
            'current_holder_id' => $this->assigned->id,
            'created_by' => $this->admin->id,
        ]);
        $this->ordinary = Document::factory()->create([
            'title' => 'Ordinary Affidavit',
            'status' => DocumentStatus::ForDrafting,
            'created_by' => $this->admin->id,
        ]);
    }

    // ── Roles ───────────────────────────────────────────────

    public function test_a_lawyer_has_lawyer_duties_but_no_administrator_tools(): void
    {
        $this->actingAs($this->lawyer)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($this->lawyer)->get(route('admin.backups.index'))->assertForbidden();
        $this->actingAs($this->lawyer)->get(route('admin.permissions'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.permissions'))->assertOk()->assertSee('Permission matrix');

        $review = Document::factory()->create(['status' => DocumentStatus::ForReview, 'current_holder_id' => $this->lawyer->id]);
        $file = DocumentAttachment::create([
            'document_id' => $review->id, 'version' => 1, 'original_name' => 'draft.pdf', 'path' => 'documents/x.pdf',
            'mime_type' => 'application/pdf', 'size' => 10, 'uploaded_by' => $this->staff->id,
        ]);

        $this->actingAs($this->lawyer)->post(route('documents.movements.store', $review), [
            'action' => 'approved', 'attachment_id' => $file->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(DocumentStatus::Approved, $review->fresh()->status);
        $this->assertTrue($file->fresh()->is_final);
    }

    public function test_staff_cannot_approve_or_mark_records_confidential(): void
    {
        $review = Document::factory()->create(['status' => DocumentStatus::ForReview]);
        $this->actingAs($this->staff)->post(route('documents.movements.store', $review), ['action' => 'approved'])
            ->assertSessionHasErrors('action');

        // the checkbox is ignored for staff — the value stays as it was
        $this->actingAs($this->assigned)->put(route('documents.update', $this->secret), [
            ...$this->detailsOf($this->secret), 'is_confidential' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($this->secret->fresh()->is_confidential);

        $this->actingAs($this->lawyer)->put(route('documents.update', $this->ordinary), [
            ...$this->detailsOf($this->ordinary), 'is_confidential' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($this->ordinary->fresh()->is_confidential);
    }

    // ── Direct URLs ─────────────────────────────────────────

    public function test_unassigned_staff_cannot_open_a_confidential_document_by_any_url(): void
    {
        $file = DocumentAttachment::create([
            'document_id' => $this->secret->id, 'version' => 1, 'original_name' => 'secret.pdf', 'path' => 'documents/secret.pdf',
            'mime_type' => 'application/pdf', 'size' => 10, 'uploaded_by' => $this->assigned->id,
        ]);
        Storage::disk('local')->put('documents/secret.pdf', 'x');

        $as = $this->actingAs($this->staff);
        $as->get(route('documents.show', $this->secret))->assertForbidden();
        $as->get(route('documents.edit', $this->secret))->assertForbidden();
        $as->put(route('documents.update', $this->secret), $this->detailsOf($this->secret))->assertForbidden();
        $as->post(route('documents.movements.store', $this->secret), ['action' => 'submitted_for_review'])->assertForbidden();
        $as->get(route('attachments.download', $file))->assertForbidden();
        $as->post(route('notarial.store', $this->secret), [])->assertForbidden();

        $this->assertSame(6, ActivityLog::where('action', 'access_denied')->where('user_id', $this->staff->id)->count(), 'each refusal is logged');
        $this->assertSame(0, $this->secret->movements()->count(), 'nothing was changed');
    }

    public function test_assigned_people_and_the_admin_can_open_it_and_the_opening_is_logged(): void
    {
        $this->actingAs($this->assigned)->get(route('documents.show', $this->secret))->assertOk()->assertSee('Confidential');
        $this->actingAs($this->admin)->get(route('documents.show', $this->secret))->assertOk();

        $this->assertSame(2, ActivityLog::where('action', 'confidential_viewed')->count());

        // someone who handled it in the past keeps access
        DocumentMovement::create([
            'document_id' => $this->ordinary->id, 'action' => 'forwarded', 'to_user_id' => $this->staff->id,
            'acted_by' => $this->admin->id, 'acted_at' => now(),
        ]);
        $this->ordinary->update(['is_confidential' => true]);
        $this->actingAs($this->staff)->get(route('documents.show', $this->ordinary))->assertOk();
    }

    public function test_ordinary_documents_stay_open_to_all_staff_in_open_mode(): void
    {
        $this->actingAs($this->staff)->get(route('documents.show', $this->ordinary))->assertOk();
    }

    // ── Lists, search, reports, dashboard ───────────────────

    public function test_confidential_documents_are_left_out_of_every_list_for_unassigned_staff(): void
    {
        $as = $this->actingAs($this->staff);

        $this->assertSame(['Ordinary Affidavit'], $as->get(route('documents.index'))->viewData('documents')->pluck('title')->all());
        $as->get(route('search', ['q' => 'Compromise']))->assertOk()->assertDontSee('Confidential Compromise Agreement');
        $as->get(route('search', ['q' => $this->secret->tracking_code]))->assertOk(); // no redirect into a 403
        $csv = $as->get(route('reports.documents', ['format' => 'csv', 'from' => '2000-01-01']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Ordinary Affidavit', $csv);
        $this->assertStringNotContainsString('Compromise', $csv);
        $as->get(route('clients.show', $this->secret->client_id))->assertOk()->assertDontSee('Confidential Compromise Agreement');
        $this->assertSame(1, $as->get(route('dashboard'))->viewData('stats')['in_process']);

        // the admin and the assigned person still see it everywhere
        $this->assertSame(2, $this->actingAs($this->admin)->get(route('documents.index'))->viewData('documents')->total());
        $this->assertSame(2, $this->actingAs($this->assigned)->get(route('dashboard'))->viewData('stats')['in_process']);
    }

    public function test_a_confidential_case_hides_its_documents_except_from_its_handling_lawyer(): void
    {
        $case = LegalCase::factory()->create(['is_confidential' => true, 'handling_attorney_id' => $this->lawyer->id, 'created_by' => $this->admin->id]);
        $document = Document::factory()->forCase($case)->create(['created_by' => $this->admin->id]);

        $this->actingAs($this->staff)->get(route('cases.show', $case))->assertForbidden();
        $this->actingAs($this->staff)->get(route('documents.show', $document))->assertForbidden();
        $this->assertSame(0, $this->actingAs($this->staff)->get(route('cases.index'))->viewData('cases')->total());

        $this->actingAs($this->lawyer)->get(route('cases.show', $case))->assertOk();
        $this->actingAs($this->lawyer)->get(route('documents.show', $document))->assertOk();
        $this->assertTrue($document->isConfidential(), 'a document inherits its case\'s confidentiality');
    }

    public function test_staff_cannot_file_a_document_under_a_case_they_cannot_see(): void
    {
        $case = LegalCase::factory()->create(['is_confidential' => true, 'created_by' => $this->admin->id]);

        $this->actingAs($this->staff)->post(route('documents.store'), [
            'title' => 'Sneaky filing',
            'document_type_id' => DocumentType::create(['name' => 'Letter'])->id,
            'client_id' => $case->client_id,
            'legal_case_id' => $case->id,
            'date_received' => today()->toDateString(),
            'current_holder_id' => $this->staff->id,
        ])->assertSessionHasErrors('legal_case_id');
    }

    public function test_the_notarial_register_keeps_every_entry_but_does_not_link_to_restricted_documents(): void
    {
        NotarialEntry::create([
            'document_id' => $this->secret->id, 'notary_id' => $this->admin->id, 'notarized_on' => today(),
            'act_type' => 'acknowledgment', 'doc_no' => 1, 'page_no' => 1, 'book_no' => 'I', 'series' => 2026,
            'principals' => [['name' => 'Juan Dela Cruz']], 'recorded_by' => $this->admin->id,
        ]);

        $this->actingAs($this->staff)->get(route('notarial.index'))
            ->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertDontSee(route('documents.show', $this->secret));
    }

    // ── Assigned-only mode (if the firm chooses it) ─────────

    public function test_assigned_mode_limits_lawyers_and_staff_to_their_own_records(): void
    {
        config(['dts.access_mode' => 'assigned']);

        $this->actingAs($this->staff)->get(route('documents.show', $this->ordinary))->assertForbidden();
        $this->assertSame(0, $this->actingAs($this->staff)->get(route('documents.index'))->viewData('documents')->total());
        $this->assertSame(['Confidential Compromise Agreement'], $this->actingAs($this->assigned)->get(route('documents.index'))->viewData('documents')->pluck('title')->all());
        $this->assertSame(2, $this->actingAs($this->admin)->get(route('documents.index'))->viewData('documents')->total());
    }

    private function detailsOf(Document $document): array
    {
        return [
            'title' => $document->title,
            'document_type_id' => $document->document_type_id,
            'client_id' => $document->client_id,
            'legal_case_id' => $document->legal_case_id,
            'date_received' => $document->date_received->toDateString(),
        ];
    }
}
