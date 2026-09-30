<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! BackupService::zipAvailable()) {
            $this->markTestSkipped('The PHP zip extension is not enabled.');
        }

        Storage::fake('local');
    }

    public function test_an_administrator_can_create_a_backup(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.backups.store'))->assertSessionHas('success');

        $this->assertCount(1, app(BackupService::class)->list());
        $this->assertDatabaseHas('activity_logs', ['action' => 'backup_created']);
    }

    public function test_restoring_brings_back_deleted_records_and_signs_everyone_out(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create(['first_name' => 'Maria Clara', 'last_name' => 'Santos']);

        $name = app(BackupService::class)->create($admin);
        $client->forceDelete();
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);

        $this->actingAs($admin)->post(route('admin.backups.restore'), [
            'backup' => $name,
            'confirm' => 'RESTORE',
            'password' => 'password',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'last_name' => 'Santos']);
        $this->assertGuest();
    }

    public function test_restore_requires_typing_restore_and_the_correct_password(): void
    {
        $admin = User::factory()->admin()->create();
        $name = app(BackupService::class)->create($admin);

        $this->actingAs($admin)->post(route('admin.backups.restore'), [
            'backup' => $name, 'confirm' => 'restore', 'password' => 'wrong',
        ])->assertSessionHasErrors(['confirm', 'password']);
    }
}
