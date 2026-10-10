<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 11C: valid IDs on clients, stored privately, masked outside the profile. */
class ClientIdTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_staff_can_add_a_valid_id_with_a_scan(): void
    {
        $staff = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($staff)->post(route('clients.ids.store', $client), [
            'id_type' => "Driver's License",
            'id_number' => 'D12-88-013540',
            'scan' => UploadedFile::fake()->createWithContent('license.pdf', self::PDF),
        ])->assertSessionHasNoErrors();

        $id = ClientId::firstOrFail();
        $this->assertSame('•••• 3540', $id->masked);
        $this->assertSame("Driver's License No. D12-88-013540", $id->label);
        Storage::disk('local')->assertExists($id->scan_path);
    }

    public function test_viewing_a_scan_requires_sign_in_and_is_logged(): void
    {
        $staff = User::factory()->create();
        $client = Client::factory()->create();
        $this->actingAs($staff)->post(route('clients.ids.store', $client), [
            'id_type' => 'Passport', 'id_number' => 'P1234567A',
            'scan' => UploadedFile::fake()->createWithContent('passport.pdf', self::PDF),
        ]);
        $id = ClientId::firstOrFail();

        $this->actingAs($staff)->get(route('client-ids.scan', $id))->assertOk();
        $this->assertDatabaseHas('activity_logs', ['action' => 'id_viewed', 'user_id' => $staff->id]);

        auth()->logout();
        $this->get(route('client-ids.scan', $id))->assertRedirect('/login');
    }

    public function test_only_the_person_who_added_it_or_an_admin_can_remove_an_id(): void
    {
        $staff = User::factory()->create();
        $otherStaff = User::factory()->create();
        $client = Client::factory()->create();
        $this->actingAs($staff)->post(route('clients.ids.store', $client), ['id_type' => 'UMID', 'id_number' => '0111-2222333-4']);
        $id = ClientId::firstOrFail();

        $this->actingAs($otherStaff)->delete(route('client-ids.destroy', $id))->assertForbidden();
        $this->actingAs($staff)->delete(route('client-ids.destroy', $id))->assertSessionHas('success');
        $this->assertDatabaseCount('client_ids', 0);
    }
}
