<?php

namespace Database\Seeders;

use App\Models\Coa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'manage_users',
            'manage_roles',
            'manage_permissions',
            'manage_wallets',
            'view_coas',
            'view_wallets',
            'view_transactions',
            'create_transactions',
            'edit_transactions',
            'delete_transactions',
        ];

        $businessEntities = [
            'sales',
            'purchases',
            'subscription_invoices',
            'retail_invoices',
            'corporate_customers',
            'retail_customers',
            'internet_packages',
            'vendors',
            'trouble_tickets',
            'inventory_items',
        ];

        $sopPermissions = [
            'view_sops',
            'create_sops',
            'edit_sops',
            'delete_sops',
        ];

        $permissions = array_merge($permissions, $sopPermissions);

        $businessActions = ['view', 'create', 'edit', 'delete'];
        foreach ($businessEntities as $entity) {
            foreach ($businessActions as $action) {
                $permissions[] = "{$action}_{$entity}";
            }
        }

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        $roleNames = [
            'Administrator',
            'Direktur',
            'General Manager',
            'HR Manager',
            'Finance',
            'Sales',
            'Technician',
        ];

        $roles = [];
        foreach ($roleNames as $name) {
            $roles[$name] = Role::firstOrCreate(['name' => $name]);
        }

        $roles['Administrator']->permissions()->syncWithoutDetaching(Permission::all());

        $roles['Direktur']->permissions()->syncWithoutDetaching(Permission::all());

        $financeViewCreate = Permission::query()
            ->whereIn('name', [
                'view_coas',
                'view_wallets',
                'view_transactions',
                'create_transactions',
            ])
            ->orWhere(fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', 'view_%'))
                ->orWhere(fn ($q) => $q->where('name', 'like', 'create_%')))
            ->get();

        $roles['Finance']->permissions()->syncWithoutDetaching($financeViewCreate->pluck('id'));

        $businessFull = Permission::query()
            ->whereIn('name', collect($businessEntities)
                ->flatMap(fn (string $entity) => array_map(fn (string $action) => "{$action}_{$entity}", $businessActions))
                ->all())
            ->get();

        foreach (['General Manager', 'HR Manager', 'Sales', 'Technician'] as $roleName) {
            $roles[$roleName]->permissions()->syncWithoutDetaching($businessFull->pluck('id'));
        }

        $sopView = Permission::where('name', 'view_sops')->first();
        foreach (['General Manager', 'HR Manager', 'Finance', 'Sales', 'Technician'] as $roleName) {
            if ($sopView) {
                $roles[$roleName]->permissions()->syncWithoutDetaching([$sopView->id]);
            }
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@cendana.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'role_id' => $roles['Administrator']->id,
            ],
        );

        $user = User::query()->updateOrCreate(
            ['email' => 'user@cendana.com'],
            [
                'name' => 'User Biasa',
                'password' => 'password',
                'role_id' => $roles['Finance']->id,
            ],
        );

        $tunai = Wallet::firstOrCreate(['name' => 'Tunai'], ['balance' => 0]);
        $bri = Wallet::firstOrCreate(['name' => 'BRI'], ['balance' => 0]);
        $bca = Wallet::firstOrCreate(['name' => 'BCA'], ['balance' => 0]);
        $gopay = Wallet::firstOrCreate(['name' => 'GoPay'], ['balance' => 0]);

        $this->call(CoaSeeder::class);

        $coas = Coa::all()->keyBy('code');

        if (Transaction::query()->count() === 0) {
            $transactions = [];
            $now = now();
            for ($day = 30; $day >= 0; $day--) {
                $date = $now->copy()->subDays($day);

                if ($date->isSaturday() || $date->isSunday()) {
                    continue;
                }

                if (rand(0, 2) === 0) {
                    $transactions[] = [
                        'name' => 'Pendapatan Harian',
                        'user_id' => $admin->id,
                        'wallet_id' => $bri->id,
                        'coa_id' => $coas['40100']->id,
                        'amount' => rand(15, 50) * 100000,
                        'transaction_date' => $date->format('Y-m-d'),
                    ];
                }

                $transactions[] = [
                    'name' => 'Makan Siang',
                    'user_id' => $admin->id,
                    'wallet_id' => $tunai->id,
                    'coa_id' => $coas['60220']->id,
                    'amount' => rand(15, 50) * 1000,
                    'transaction_date' => $date->format('Y-m-d'),
                ];

                if (rand(0, 1)) {
                    $transactions[] = [
                        'name' => 'Transportasi',
                        'user_id' => $admin->id,
                        'wallet_id' => $gopay->id,
                        'coa_id' => $coas['60300']->id,
                        'amount' => rand(20, 100) * 1000,
                        'transaction_date' => $date->format('Y-m-d'),
                    ];
                }

                if ($day % 7 === 0) {
                    $transactions[] = [
                        'name' => 'Belanja Bulanan',
                        'user_id' => $admin->id,
                        'wallet_id' => $gopay->id,
                        'coa_id' => $coas['60400']->id,
                        'amount' => rand(30, 75) * 10000,
                        'transaction_date' => $date->format('Y-m-d'),
                    ];
                }

                if ($day % 14 === 0) {
                    $transactions[] = [
                        'name' => 'Transfer ke BRI',
                        'user_id' => $admin->id,
                        'wallet_id' => $gopay->id,
                        'to_wallet_id' => $bri->id,
                        'coa_id' => $coas['10201']->id,
                        'amount' => rand(50, 150) * 10000,
                        'transaction_date' => $date->format('Y-m-d'),
                    ];
                }
            }

            foreach ($transactions as $t) {
                Transaction::create($t);
            }
        }
    }
}
