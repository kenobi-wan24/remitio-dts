<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Enums\PriorityGroup;
use App\Enums\Urgency;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\DocumentType;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revision 1 (R4) — professor's critique items 4 and 8: urgency levels and prioritization.
 */
class PriorityTest extends TestCase
{
    use RefreshDatabase;

    private User $lawyer;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lawyer = User::factory()->lawyer()->create();
        $this->staff = User::factory()->create();
    }

    private function doc(array $attributes = [], ?string $lastMovedDaysAgo = null): Document
    {
        $document = Document::factory()->create([
            'status' => DocumentStatus::ForDrafting,
            'current_holder_id' => $this->staff->id,
            ...$attributes,
        ]);

        DocumentMovement::create([
            'document_id' => $document->id, 'action' => 'drafting_started', 'to_status' => 'for_drafting',
            'to_user_id' => $this->staff->id, 'acted_by' => $this->staff->id,
            'acted_at' => now()->subDays((int) ($lastMovedDaysAgo ?? 1)),
        ]);

        return $document;
    }

    // ── Urgency ─────────────────────────────────────────────

    public function test_a_lawyer_changes_urgency_with_a_reason_and_it_is_kept_in_the_history(): void
    {
        $document = $this->doc();

        $this->actingAs($this->lawyer)->post(route('documents.urgency', $document), [
            'urgency' => 'critical', 'reason' => 'Hearing moved to tomorrow',
        ])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(Urgency::Critical, $document->urgency);
        $this->assertSame(DocumentStatus::ForDrafting, $document->status, 'status unchanged');
        $this->assertSame($this->staff->id, $document->current_holder_id, 'holder unchanged');

        $movement = $document->movements()->first();
        $this->assertSame(MovementAction::UrgencyChanged, $movement->action);
        $this->assertStringContainsString('Normal → Critical', $movement->remarks);
        $this->assertStringContainsString('Hearing moved to tomorrow', $movement->remarks);
    }

    public function test_staff_cannot_change_urgency_and_a_reason_is_always_required(): void
    {
        $document = $this->doc();

        $this->actingAs($this->staff)->post(route('documents.urgency', $document), ['urgency' => 'critical', 'reason' => 'x'])
            ->assertForbidden();
        $this->actingAs($this->lawyer)->post(route('documents.urgency', $document), ['urgency' => 'high'])
            ->assertSessionHasErrorsIn('priority', 'reason');
        $this->actingAs($this->lawyer)->post(route('documents.urgency', $document), ['urgency' => 'normal', 'reason' => 'same'])
            ->assertSessionHasErrorsIn('priority', 'urgency');

        $this->assertSame(Urgency::Normal, $document->fresh()->urgency);
    }

    public function test_case_urgency_is_set_by_lawyers_and_new_documents_start_with_it(): void
    {
        $case = LegalCase::factory()->create(['urgency' => 'high', 'created_by' => $this->lawyer->id]);

        $this->actingAs($this->staff)->post(route('documents.store'), [
            'title' => 'Urgent Motion',
            'document_type_id' => DocumentType::create(['name' => 'Motion'])->id,
            'client_id' => $case->client_id,
            'legal_case_id' => $case->id,
            'date_received' => today()->toDateString(),
            'current_holder_id' => $this->staff->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(Urgency::High, Document::where('title', 'Urgent Motion')->first()->urgency);

        // staff cannot change the case's urgency through the form
        $this->actingAs($this->staff)->put(route('cases.update', $case), [
            'title' => $case->title, 'client_id' => $case->client_id, 'case_type' => $case->case_type->value,
            'status' => $case->status->value, 'date_opened' => $case->date_opened->toDateString(), 'urgency' => 'low',
        ])->assertSessionHasNoErrors();
        $this->assertSame(Urgency::High, $case->fresh()->urgency);
    }

    // ── On hold ─────────────────────────────────────────────

    public function test_on_hold_needs_a_reason_and_resuming_clears_it(): void
    {
        $document = $this->doc();

        $this->actingAs($this->staff)->post(route('documents.hold', $document), [])->assertSessionHasErrorsIn('priority', 'reason');

        $this->actingAs($this->staff)->post(route('documents.hold', $document), ['reason' => "Waiting for the client's TCT"])
            ->assertSessionHasNoErrors();
        $this->assertTrue($document->fresh()->isOnHold());
        $this->assertSame(PriorityGroup::OnHold, $document->fresh()->priorityGroup());

        $this->actingAs($this->staff)->post(route('documents.hold', $document), [])->assertSessionHasNoErrors();
        $this->assertFalse($document->fresh()->isOnHold());

        $this->assertSame(
            [MovementAction::Resumed, MovementAction::PutOnHold],
            $document->movements()->take(2)->get()->pluck('action')->all(),
        );
    }

    public function test_released_documents_cannot_be_put_on_hold(): void
    {
        $document = $this->doc(['status' => DocumentStatus::Released, 'current_holder_id' => null]);

        $this->actingAs($this->staff)->post(route('documents.hold', $document), ['reason' => 'x'])->assertStatus(422);
    }

    // ── Prioritization ──────────────────────────────────────

    public function test_the_queue_groups_by_priority_and_sorts_by_urgency_then_waiting_time(): void
    {
        $routineNew = $this->doc(['title' => 'Routine (new)'], '1');
        $routineOld = $this->doc(['title' => 'Routine (waiting longest)'], '9');
        $critical = $this->doc(['title' => 'Critical', 'urgency' => 'critical'], '1');
        $high = $this->doc(['title' => 'High', 'urgency' => 'high'], '2');
        $low = $this->doc(['title' => 'Low', 'urgency' => 'low'], '20');
        $held = $this->doc(['title' => 'Critical but on hold', 'urgency' => 'critical', 'on_hold_since' => now(), 'on_hold_reason' => 'Client']);

        $queue = collect($this->actingAs($this->staff)->get(route('dashboard'))->assertOk()->viewData('actionQueue'));

        $this->assertSame(['Immediate Attention', 'High Priority', 'Routine', 'On Hold'], $queue->pluck('label')->all());
        $this->assertSame(['Critical'], $queue[0]['documents']->pluck('title')->all());
        $this->assertSame(
            ['Routine (waiting longest)', 'Routine (new)', 'Low'],
            $queue[2]['documents']->pluck('title')->all(),
            'Normal before Low; then waiting longest first',
        );
        $this->assertSame(['Critical but on hold'], $queue[3]['documents']->pluck('title')->all(), 'on hold does not lower urgency, but it waits apart');
    }

    public function test_my_documents_list_sorts_by_priority_by_default(): void
    {
        $this->doc(['title' => 'B normal'], '5');
        $this->doc(['title' => 'A critical', 'urgency' => 'critical'], '1');
        $this->doc(['title' => 'C on hold', 'urgency' => 'critical', 'on_hold_since' => now(), 'on_hold_reason' => 'x']);

        $titles = $this->actingAs($this->staff)->get(route('documents.index', ['holder' => 'me']))->viewData('documents')->pluck('title')->all();

        $this->assertSame(['A critical', 'B normal', 'C on hold'], $titles);
    }

    // ── Deadlines (off unless the firm turns them on) ───────

    public function test_deadlines_are_ignored_while_turned_off(): void
    {
        $document = $this->doc(['deadline' => today()]);

        $this->assertNull($document->activeDeadline());
        $this->assertSame(PriorityGroup::Routine, $document->priorityGroup());
        $this->actingAs($this->staff)->get(route('documents.edit', $document))->assertDontSee('Deadline (optional)');
    }

    public function test_when_turned_on_deadlines_drive_the_priority_groups(): void
    {
        config(['dts.deadlines' => true]);

        $this->assertSame(PriorityGroup::Immediate, $this->doc(['deadline' => today()])->priorityGroup());
        $this->assertSame(PriorityGroup::Immediate, $this->doc(['deadline' => today()->subDay()])->priorityGroup());
        $this->assertSame(PriorityGroup::DueSoon, $this->doc(['deadline' => today()->addDays(2)])->priorityGroup());
        $this->assertSame(PriorityGroup::Routine, $this->doc(['deadline' => today()->addDays(10)])->priorityGroup());

        $document = $this->doc();
        $this->actingAs($this->staff)->get(route('documents.edit', $document))->assertSee('Deadline (optional)');
        $this->actingAs($this->staff)->put(route('documents.update', $document), [
            'title' => $document->title, 'document_type_id' => $document->document_type_id,
            'client_id' => $document->client_id, 'legal_case_id' => $document->legal_case_id,
            'date_received' => $document->date_received->toDateString(), 'deadline' => today()->addDays(5)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(today()->addDays(5)->toDateString(), $document->fresh()->deadline->toDateString());
    }
}
