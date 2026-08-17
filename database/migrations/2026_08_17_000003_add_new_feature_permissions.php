<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $entities = ['trouble_tickets', 'inventory_items'];

    public function up(): void
    {
        $created = [];

        foreach ($this->entities as $entity) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $name = "{$action}_{$entity}";
                $created[$name] = Permission::firstOrCreate(['name' => $name]);
            }
        }

        $ids = array_column($created, 'id');

        foreach (['Administrator', 'Direktur'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }

        $operasional = ['General Manager', 'Finance', 'Sales', 'Technician'];
        $roles = Role::whereIn('name', $operasional)->get();
        foreach ($roles as $role) {
            foreach (['view', 'create'] as $action) {
                foreach ($this->entities as $entity) {
                    $role->permissions()->syncWithoutDetaching([$created["{$action}_{$entity}"]->id]);
                }
            }
        }
    }

    public function down(): void
    {
        $names = collect($this->entities)
            ->flatMap(fn (string $entity): array => [
                "view_{$entity}",
                "create_{$entity}",
                "edit_{$entity}",
                "delete_{$entity}",
            ])
            ->all();

        Permission::whereIn('name', $names)->get()->each(function (Permission $permission): void {
            $permission->roles()->detach();
            $permission->delete();
        });
    }
};
