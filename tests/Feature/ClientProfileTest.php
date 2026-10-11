<?php

namespace Tests\Feature;

use App\Enums\CaseType;
use App\Models\Client;
use App\Models\ClientId;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Revision 2 - Phase 2: new individual / company fields, walk-in clients, client page. */
class ClientProfileTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function individual(array $overrides = []): array
    {
        return [
            'category' => 'regular',
            'client_type' => 'individual',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'suffix' => 'Jr.',
            'civil_status' => 'single',
            'gender' => 'male',
            'contact_number' => '09171112222',
            ...$overrides,
        ];
    }

    private function company(array $overrides = []): array
    {
        return [
            'category' => 'retainer',
            'client_type' => 'company',
            'company_name' => 'Mindanao Realty Corp.',
            'representative' => 'Ana Reyes, General Manager',
            'business_permit_number' => 'BP-2026-00123',
            'tin' => '123-456-789-000',
            'contact_number' => '082 123 4567',
            ...$overrides,
        ];
    }

    public function test_an_individual_is_saved_with_the_new_name_and_status_fields(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('clients.store'), $this->individual())
            ->assertSessionHasNoErrors();

        $client = Client::firstOrFail();
        $this->assertSame('Santos', $client->middle_name);
        $this->assertSame('Jr.', $client->suffix);
        $this->assertSame('single', $client->civil_status->value);
        $this->assertSame('male', $client->gender->value);
        $this->assertSame('Juan Santos Dela Cruz Jr.', $client->display_name);
        $this->assertSame('Dela Cruz, Juan Santos Jr.', $client->formal_name);
        $this->assertFalse($client->notarization_only);
        $this->assertFalse($client->is_retainer);
    }

    public function test_civil_status_and_gender_are_required_for_individuals(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('clients.store'), $this->individual(['civil_status' => '', 'gender' => '']))
            ->assertSessionHasErrors(['civil_status', 'gender']);
    }

    public function test_a_married_client_must_have_spouse_details(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->post(route('clients.store'), $this->individual(['civil_status' => 'married']))
            ->assertSessionHasErrors(['spouse_last_name', 'spouse_first_name']);

        $this->actingAs($staff)->post(route('clients.store'), $this->individual([
            'civil_status' => 'married',
            'spouse_last_name' => 'Dela Cruz',
            'spouse_first_name' => 'Maria',
            'spouse_middle_name' => 'Reyes',
            'spouse_contact_number' => '09175556666',
        ]))->assertSessionHasNoErrors();

        $client = Client::firstOrFail();
        $this->assertTrue($client->isMarried());
        $this->assertSame('Maria Reyes Dela Cruz', $client->spouse_name);
    }

    public function test_spouse_details_are_cleared_when_the_client_is_not_married(): void
    {
        $staff = User::factory()->create();
        $client = Client::factory()->married()->create();

        $this->actingAs($staff)->put(route('clients.update', $client), $this->individual([
            'last_name' => $client->last_name,
            'first_name' => $client->first_name,
            'civil_status' => 'widowed',
            'spouse_last_name' => 'Should',
            'spouse_first_name' => 'Vanish',
        ]))->assertSessionHasNoErrors();

        $client->refresh();
        $this->assertNull($client->spouse_last_name);
        $this->assertNull($client->spouse_first_name);
    }

    public function test_an_optional_id_can_be_added_while_creating_a_client(): void
    {
        $this->actingAs(User::factory()->create())->post(route('clients.store'), $this->individual([
            'id_type' => "Driver's License",
            'id_number' => 'D12-88-013540',
            'scan' => UploadedFile::fake()->createWithContent('license.pdf', self::PDF),
        ]))->assertSessionHasNoErrors();

        $id = ClientId::firstOrFail();
        $this->assertSame(Client::firstOrFail()->id, $id->client_id);
        $this->assertSame('•••• 3540', $id->masked);
        Storage::disk('local')->assertExists($id->scan_path);
    }

    public function test_id_type_and_number_must_be_given_together(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('clients.store'), $this->individual(['id_type' => 'Passport']))
            ->assertSessionHasErrors('id_number');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_a_company_needs_business_name_and_representative(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->post(route('clients.store'), $this->company(['company_name' => '', 'representative' => '']))
            ->assertSessionHasErrors(['company_name', 'representative']);

        $this->actingAs($staff)->post(route('clients.store'), $this->company())->assertSessionHasNoErrors();

        $client = Client::firstOrFail();
        $this->assertSame('Ana Reyes, General Manager', $client->representative);
        $this->assertSame('BP-2026-00123', $client->business_permit_number);
        $this->assertSame('123-456-789-000', $client->tin);
        $this->assertTrue($client->is_retainer);
        $this->assertNull($client->civil_status);
        $this->assertSame('Mindanao Realty Corp.', $client->display_name);
    }

    public function test_a_bad_tin_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('clients.store'), $this->company(['tin' => 'abc']))
            ->assertSessionHasErrors('tin');
    }

    public function test_the_same_tin_is_flagged_as_a_possible_duplicate(): void
    {
        Client::factory()->company()->create(['tin' => '123-456-789-000']);

        $this->actingAs(User::factory()->create())
            ->post(route('clients.store'), $this->company(['company_name' => 'Another Name Inc.', 'contact_number' => '082 999 0000']))
            ->assertSessionHas('duplicates');
    }

    public function test_a_walk_in_client_needs_a_valid_id_but_not_contact_details(): void
    {
        $staff = User::factory()->create();
        $payload = $this->individual([
            'category' => 'walk_in',
            'client_type' => 'company', // ignored: walk-ins are always individuals
            'civil_status' => '', 'gender' => '', 'contact_number' => '',
        ]);

        $this->actingAs($staff)->post(route('clients.store'), $payload)
            ->assertSessionHasErrors(['id_type', 'id_number']);

        $this->actingAs($staff)->post(route('clients.store'), [...$payload, 'id_type' => 'Passport', 'id_number' => 'P1234567A'])
            ->assertSessionHasNoErrors();

        $client = Client::firstOrFail();
        $this->assertTrue($client->notarization_only);
        $this->assertFalse($client->is_retainer);
        $this->assertSame('individual', $client->client_type->value);
        $this->assertSame('Walk-in (notarization only)', $client->engagementLabel());
        $this->assertCount(1, $client->validIds);
    }

    public function test_a_walk_in_client_cannot_have_a_case(): void
    {
        $staff = User::factory()->create();
        $walkIn = Client::factory()->walkIn()->create();
        $regular = Client::factory()->create();

        $case = [
            'title' => 'Sample matter',
            'case_type' => CaseType::Civil->value,
            'status' => 'open',
            'date_opened' => today()->toDateString(),
        ];

        $this->actingAs($staff)->post(route('cases.store'), [...$case, 'client_id' => $walkIn->id])
            ->assertSessionHasErrors('client_id');

        $this->actingAs($staff)->post(route('cases.store'), [...$case, 'client_id' => $regular->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($staff)->get(route('cases.create'))
            ->assertOk()
            ->assertDontSee($walkIn->client_code)
            ->assertSee($regular->client_code);
    }

    public function test_a_client_with_cases_cannot_be_changed_to_walk_in(): void
    {
        $client = Client::factory()->create();
        LegalCase::factory()->create(['client_id' => $client->id]);

        $this->actingAs(User::factory()->create())->put(route('clients.update', $client), $this->individual([
            'category' => 'walk_in',
            'last_name' => $client->last_name,
            'first_name' => $client->first_name,
            'id_type' => 'Passport', 'id_number' => 'P1234567A',
        ]))->assertSessionHasErrors('category');

        $this->assertFalse($client->fresh()->notarization_only);
    }

    public function test_the_clients_list_can_be_filtered_to_walk_ins(): void
    {
        $walkIn = Client::factory()->walkIn()->create(['last_name' => 'Walkerson']);
        Client::factory()->create(['last_name' => 'Regularson']);

        $this->actingAs(User::factory()->create())
            ->get(route('clients.index', ['engagement' => 'walk_in']))
            ->assertOk()
            ->assertSee('Walkerson')
            ->assertDontSee('Regularson');
    }

    public function test_the_client_page_shows_each_documents_case_and_an_add_document_button(): void
    {
        $staff = User::factory()->create();
        $client = Client::factory()->create();
        $case = LegalCase::factory()->create(['client_id' => $client->id, 'title' => 'Boundary dispute']);
        $withCase = Document::factory()->create(['client_id' => $client->id, 'legal_case_id' => $case->id, 'title' => 'Answer to complaint']);
        $noCase = Document::factory()->create(['client_id' => $client->id, 'legal_case_id' => null, 'title' => 'Loose affidavit']);

        $this->actingAs($staff)->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Add document')
            ->assertSee(route('documents.create', ['client_id' => $client->id]), false)
            ->assertSee($case->case_code)
            ->assertSee('Boundary dispute')
            ->assertSee('No case');
    }

    public function test_the_add_document_link_preselects_the_client(): void
    {
        $client = Client::factory()->create();
        DocumentType::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('documents.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee($client->client_code);
    }

    public function test_the_new_client_form_offers_the_new_fields(): void
    {
        $this->actingAs(User::factory()->create())->get(route('clients.create'))
            ->assertOk()
            ->assertSee('Middle Name')
            ->assertSee('Civil Status')
            ->assertSee('Spouse Details')
            ->assertSee('Representative')
            ->assertSee('Business Permit No.')
            ->assertSee('TIN')
            ->assertSee('Walk-in')
            ->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_the_edit_form_shows_the_saved_values(): void
    {
        $client = Client::factory()->married()->create(['middle_name' => 'Santos', 'spouse_first_name' => 'Maria']);

        $this->actingAs(User::factory()->create())->get(route('clients.edit', $client))
            ->assertOk()
            ->assertSee('Santos')
            ->assertSee('Maria');
    }

    public function test_the_client_page_shows_spouse_and_company_details(): void
    {
        $staff = User::factory()->create();
        $married = Client::factory()->married()->create(['spouse_first_name' => 'Maria', 'spouse_last_name' => 'Lopez']);
        $company = Client::factory()->company()->create(['representative' => 'Pedro Penduko', 'tin' => '111-222-333-000']);

        $this->actingAs($staff)->get(route('clients.show', $married))->assertOk()->assertSee('Maria Lopez');
        $this->actingAs($staff)->get(route('clients.show', $company))->assertOk()
            ->assertSee('Pedro Penduko')->assertSee('111-222-333-000');
    }
}
