<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $permissions = [
        'view_sops',
        'create_sops',
        'edit_sops',
        'delete_sops',
    ];

    public function up(): void
    {
        $created = [];
        foreach ($this->permissions as $name) {
            $created[$name] = Permission::firstOrCreate(['name' => $name]);
        }

        $admin = Role::where('name', 'Administrator')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching(array_column($created, 'id'));
        }

        $direktur = Role::where('name', 'Direktur')->first();
        if ($direktur) {
            $direktur->permissions()->syncWithoutDetaching(array_column($created, 'id'));
        }

        $viewOnly = ['General Manager', 'HR Manager', 'Finance', 'Sales', 'Technician'];
        $roles = Role::whereIn('name', $viewOnly)->get();
        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching([$created['view_sops']->id]);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->get()->each(function (Permission $permission): void {
            $permission->roles()->detach();
            $permission->delete();
        });
    }
};
