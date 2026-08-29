<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $permissions = [
        'view_invoice_templates',
        'create_invoice_templates',
        'edit_invoice_templates',
        'delete_invoice_templates',
    ];

    public function up(): void
    {
        $created = [];
        foreach ($this->permissions as $name) {
            $created[$name] = Permission::firstOrCreate(['name' => $name]);
        }

        foreach (['Administrator', 'Direktur'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching(array_column($created, 'id'));
            }
        }

        $finance = Role::where('name', 'Finance')->first();
        if ($finance) {
            $finance->permissions()->syncWithoutDetaching([$created['view_invoice_templates']->id]);
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