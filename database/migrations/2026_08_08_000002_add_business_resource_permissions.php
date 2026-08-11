<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $entities = [
        'sales',
        'purchases',
        'subscription_invoices',
        'retail_invoices',
        'corporate_customers',
        'retail_customers',
        'internet_packages',
        'vendors',
    ];

    private array $actions = ['view', 'create', 'edit', 'delete'];

    public function up(): void
    {
        $permissions = [];
        foreach ($this->entities as $entity) {
            foreach ($this->actions as $action) {
                $name = "{$action}_{$entity}";
                $permissions[$name] = Permission::firstOrCreate(['name' => $name]);
            }
        }

        $viewCreate = array_filter(
            array_keys($permissions),
            fn (string $name): bool => str_starts_with($name, 'view_') || str_starts_with($name, 'create_')
        );

        $fullRoleNames = ['Administrator', 'Direktur', 'General Manager', 'HR Manager', 'Sales', 'Technician'];
        $roles = Role::whereIn('name', $fullRoleNames)->get();

        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching(array_column($permissions, 'id'));
        }

        $finance = Role::where('name', 'Finance')->first();
        if ($finance) {
            $finance->permissions()->syncWithoutDetaching(
                array_map(fn (string $name): int => $permissions[$name]->id, $viewCreate)
            );
        }
    }

    public function down(): void
    {
        $names = [];
        foreach ($this->entities as $entity) {
            foreach ($this->actions as $action) {
                $names[] = "{$action}_{$entity}";
            }
        }

        Permission::whereIn('name', $names)->get()->each(function (Permission $permission): void {
            $permission->roles()->detach();
            $permission->delete();
        });
    }
};