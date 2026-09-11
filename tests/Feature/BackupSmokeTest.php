<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupPage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BackupSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');

        Role::firstOrCreate(['name' => 'Administrator']);
    }

    public function test_only_admin_can_access_backup_page(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(BackupPage::class)
            ->assertSuccessful();

        $role = Role::firstOrCreate(['name' => 'Finance']);
        $user = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($user)
            ->test(BackupPage::class)
            ->assertForbidden();
    }

    public function test_run_backup_triggers_backup_command(): void
    {
        $admin = User::factory()->admin()->create();

        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run', \Mockery::any())
            ->andReturn(0);

        Livewire::actingAs($admin)
            ->test(BackupPage::class)
            ->call('runBackup')
            ->assertNotified('Backup berhasil dibuat');
    }

    public function test_list_download_and_delete_backups(): void
    {
        $admin = User::factory()->admin()->create();

        Storage::disk('backups')->put('Cendana/cendana-2026-09-11-13-25-13.zip', 'dummy');

        Livewire::actingAs($admin)
            ->test(BackupPage::class)
            ->assertSee('cendana-2026-09-11-13-25-13.zip')
            ->call('downloadBackup', 'Cendana/cendana-2026-09-11-13-25-13.zip');

        $this->assertTrue(Storage::disk('backups')->exists('Cendana/cendana-2026-09-11-13-25-13.zip'));

        Livewire::actingAs($admin)
            ->test(BackupPage::class)
            ->call('deleteBackup', 'Cendana/cendana-2026-09-11-13-25-13.zip')
            ->assertNotified('Backup dihapus');

        $this->assertFalse(Storage::disk('backups')->exists('Cendana/cendana-2026-09-11-13-25-13.zip'));

        Livewire::actingAs($admin)
            ->test(BackupPage::class)
            ->call('downloadBackup', 'Cendana/tidak-ada.zip')
            ->assertStatus(404);
    }
}
