<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/documents')->assertRedirect('/login');
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_staff_cannot_open_administrator_pages(): void
    {
        $staff = User::factory()->create();

        foreach (['/admin/users', '/admin/document-types', '/admin/activity-logs', '/admin/backups'] as $url) {
            $this->actingAs($staff)->get($url)->assertForbidden();
        }
    }

    public function test_administrators_can_open_administrator_pages(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['/admin/users', '/admin/document-types', '/admin/activity-logs', '/admin/backups'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_deactivated_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->inactive()->create(['email' => 'clerk@remitio.test']);

        $this->post('/login', ['email' => 'clerk@remitio.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_staff_cannot_delete_clients(): void
    {
        $staff = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($staff)->delete(route('clients.destroy', $client))->assertForbidden();
        $this->assertNotSoftDeleted($client);
    }

    public function test_an_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.users.toggle', $admin))->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_active);
    }
}
