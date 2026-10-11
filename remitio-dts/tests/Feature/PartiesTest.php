<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 11C: agreements and deeds must list at least two parties (each a client). */
class PartiesTest extends TestCase
{
    use RefreshDatabase;

    private function payload(DocumentType $type, Client $client, array $parties): array
    {
        $staff = User::factory()->create();
        $this->actingAs($staff);

        return [
            'title' => 'Deed of Absolute Sale',
            'document_type_id' => $type->id,
            'client_id' => $client->id,
            'date_received' => today()->toDateString(),
            'current_holder_id' => $staff->id,
            'parties' => $parties,
        ];
    }

    public function test_a_deed_needs_at_least_two_parties(): void
    {
        $deed = DocumentType::create(['name' => 'Deed', 'min_parties' => 2]);
        $vendor = Client::factory()->create();

        $this->post(route('documents.store'), $this->payload($deed, $vendor, [
            ['client_id' => $vendor->id, 'role' => 'Vendor'],
        ]))->assertSessionHasErrors('parties');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_deed_with_two_parties_is_recorded_and_found_by_either_party(): void
    {
        $deed = DocumentType::create(['name' => 'Deed', 'min_parties' => 2]);
        $vendor = Client::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        $vendee = Client::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->post(route('documents.store'), $this->payload($deed, $vendor, [
            ['client_id' => $vendor->id, 'role' => 'Vendor'],
            ['client_id' => $vendee->id, 'role' => 'Vendee'],
        ]))->assertSessionHasNoErrors();

        $document = Document::firstOrFail();
        $this->assertSame(['Vendor', 'Vendee'], $document->parties->pluck('pivot.role')->all());

        // search finds it by the second party, and the vendee's profile lists it
        $this->assertTrue(Document::search('Santos')->whereKey($document->id)->exists());
        $this->get(route('clients.show', $vendee))->assertSee($document->tracking_code);
    }

    public function test_the_same_client_cannot_be_listed_twice(): void
    {
        $deed = DocumentType::create(['name' => 'Deed', 'min_parties' => 2]);
        $vendor = Client::factory()->create();

        $this->post(route('documents.store'), $this->payload($deed, $vendor, [
            ['client_id' => $vendor->id, 'role' => 'Vendor'],
            ['client_id' => $vendor->id, 'role' => 'Vendee'],
        ]))->assertSessionHasErrors('parties.0.client_id');
    }

    public function test_other_document_types_need_no_extra_parties(): void
    {
        $affidavit = DocumentType::create(['name' => 'Affidavit']);
        $client = Client::factory()->create();

        $this->post(route('documents.store'), $this->payload($affidavit, $client, []))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('documents', 1);
    }

    public function test_a_client_who_is_a_party_cannot_be_deleted(): void
    {
        $deed = DocumentType::create(['name' => 'Deed', 'min_parties' => 2]);
        $vendor = Client::factory()->create();
        $vendee = Client::factory()->create();
        $this->post(route('documents.store'), $this->payload($deed, $vendor, [
            ['client_id' => $vendor->id], ['client_id' => $vendee->id],
        ]));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete(route('clients.destroy', $vendee))->assertSessionHas('error');
        $this->assertNotSoftDeleted($vendee);
    }
}
