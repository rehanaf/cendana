<?php

namespace Tests\Feature;

use App\Filament\Pages\MenuSop;
use App\Filament\Resources\Sops\Pages\ManageSops;
use App\Filament\Resources\Sops\SopResource;
use App\Models\Role;
use App\Models\Sop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SopSmokeTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();

        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Dijalankan pada MySQL.');
        }

        foreach (['Administrator', 'Finance', 'Sales Sementara', 'Teknisi Sementara', 'Lain'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
    }

    public function test_admin_can_view_resource_and_create_form_mounts(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ManageSops::class)
            ->assertSuccessful()
            ->mountAction('create')
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('description')
            ->assertFormFieldExists('file_path')
            ->assertFormFieldExists('is_public')
            ->assertFormFieldExists('role_id');
    }

    public function test_sop_resource_permissions_and_menu_access(): void
    {
        $admin = User::factory()->admin()->create();
        auth()->login($admin);
        $this->assertTrue(SopResource::canViewAny());
        $this->assertTrue(SopResource::canCreate());

        $role = Role::firstOrCreate(['name' => 'Sales Sementara']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $view = \App\Models\Permission::firstOrCreate(['name' => 'view_sops']);
        $role->permissions()->sync([$view->id]);

        auth()->login($user);
        $this->assertTrue(SopResource::canViewAny());
        $this->assertFalse(SopResource::canCreate());
        $this->assertTrue(MenuSop::canAccess());

        auth()->login($admin);
        $this->assertTrue(MenuSop::canAccess());
    }

    public function test_menu_sop_shows_own_role_and_public_sops(): void
    {
        $role = Role::firstOrCreate(['name' => 'Teknisi Sementara']);
        $viewPerm = \App\Models\Permission::firstOrCreate(['name' => 'view_sops']);
        $role->permissions()->syncWithoutDetaching([$viewPerm->id]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $public = Sop::create([
            'title' => 'SOP Publik',
            'is_public' => true,
            'file_path' => 'sops/public.pdf',
        ]);
        $own = Sop::create([
            'title' => 'SOP Divisi',
            'role_id' => $role->id,
            'file_path' => 'sops/own.pdf',
        ]);
        $otherRole = Role::firstOrCreate(['name' => 'Lain']);
        $foreign = Sop::create([
            'title' => 'SOP Divisi Lain',
            'role_id' => $otherRole->id,
            'file_path' => 'sops/foreign.pdf',
        ]);

        Livewire::actingAs($user)
            ->test(MenuSop::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$public, $own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }
}
