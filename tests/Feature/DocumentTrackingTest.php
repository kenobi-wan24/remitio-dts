<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function recordDocument(User $by): Document
    {
        $client = Client::factory()->create();
        $type = DocumentType::create(['name' => 'Deed']);

        $this->actingAs($by)->post(route('documents.store'), [
            'title' => 'Deed of Absolute Sale',
            'document_type_id' => $type->id,
            'client_id' => $client->id,
            'date_received' => today()->toDateString(),
            'current_holder_id' => $by->id,
        ]);

        return Document::latest('id')->firstOrFail();
    }

    public function test_recording_a_document_assigns_a_tracking_code_and_a_received_movement(): void
    {
        $staff = User::factory()->create();

        $document = $this->recordDocument($staff);

        $this->assertMatchesRegularExpression('/^DOC-\d{4}-\d{5}$/', $document->tracking_code);
        $this->assertSame(DocumentStatus::Received, $document->status);
        $this->assertSame($staff->id, $document->current_holder_id);
        $this->assertCount(1, $document->movements);
        $this->assertSame(MovementAction::Received, $document->movements->first()->action);
    }

    public function test_forwarding_updates_holder_status_and_history_together(): void
    {
        $staff = User::factory()->create();
        $attorney = User::factory()->admin()->create();
        $document = $this->recordDocument($staff);

        $this->actingAs($staff)->post(route('documents.movements.store', $document), [
            'action' => 'forwarded',
            'to_user_id' => $attorney->id,
            'to_status' => 'for_signature',
            'remarks' => 'For review and signature',
        ])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame($attorney->id, $document->current_holder_id);
        $this->assertSame(DocumentStatus::ForSignature, $document->status);
        $this->assertCount(2, $document->movements);
        $this->assertDatabaseHas('activity_logs', ['action' => 'moved', 'user_id' => $staff->id]);
    }

    public function test_a_document_cannot_be_forwarded_to_the_person_already_holding_it(): void
    {
        $staff = User::factory()->create();
        $document = $this->recordDocument($staff);

        $this->actingAs($staff)->post(route('documents.movements.store', $document), [
            'action' => 'forwarded',
            'to_user_id' => $staff->id,
        ])->assertSessionHasErrors('to_user_id');

        $this->assertCount(1, $document->fresh()->movements);
    }

    public function test_releasing_requires_remarks_and_leaves_the_document_without_a_holder(): void
    {
        $staff = User::factory()->create();
        $document = $this->recordDocument($staff);

        $this->actingAs($staff)->post(route('documents.movements.store', $document), ['action' => 'released_to_client'])
            ->assertSessionHasErrors('remarks');

        $this->actingAs($staff)->post(route('documents.movements.store', $document), [
            'action' => 'released_to_client',
            'remarks' => 'Received by the client',
        ])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(DocumentStatus::Released, $document->status);
        $this->assertNull($document->current_holder_id);
    }

    public function test_a_released_document_cannot_be_forwarded(): void
    {
        $staff = User::factory()->create();
        $other = User::factory()->create();
        $document = $this->recordDocument($staff);

        $this->actingAs($staff)->post(route('documents.movements.store', $document), [
            'action' => 'released_to_client', 'remarks' => 'Released',
        ]);

        $this->actingAs($staff)->post(route('documents.movements.store', $document), [
            'action' => 'forwarded', 'to_user_id' => $other->id,
        ])->assertSessionHasErrors('action');
    }

    public function test_movements_cannot_be_edited_or_deleted(): void
    {
        $staff = User::factory()->create();
        $document = $this->recordDocument($staff);
        $url = route('documents.movements.store', $document); // the only movements route (POST = add)

        $this->actingAs($staff)->put($url)->assertStatus(405);    // no "edit"
        $this->actingAs($staff)->delete($url)->assertStatus(405); // no "delete"
        $this->assertCount(1, $document->fresh()->movements);
    }
}
