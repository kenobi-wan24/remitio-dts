<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicatePreventionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'client_type' => 'individual',
            'first_name' => 'Maria Clara',
            'last_name' => 'Santos',
            'contact_number' => '09171234567',
            ...$overrides,
        ];
    }

    public function test_a_possible_duplicate_client_is_stopped_with_a_warning(): void
    {
        $staff = User::factory()->create();
        Client::factory()->create(['contact_number' => '09171234567']);

        $this->actingAs($staff)->post(route('clients.store'), $this->payload())
            ->assertSessionHas('duplicates');

        $this->assertDatabaseCount('clients', 1);
    }

    public function test_the_user_can_confirm_it_is_a_different_client(): void
    {
        $staff = User::factory()->create();
        Client::factory()->create(['contact_number' => '09171234567']);

        $this->actingAs($staff)->post(route('clients.store'), $this->payload(['confirm_duplicate' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('clients', 2);
    }

    public function test_client_codes_are_generated_automatically(): void
    {
        $client = Client::factory()->create();

        $this->assertMatchesRegularExpression('/^CL-\d{4}-\d{4}$/', $client->client_code);
    }
}
