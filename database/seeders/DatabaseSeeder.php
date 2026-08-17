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
            Permission::create(['name' => $name]);
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
            $roles[$name] = Role::create(['name' => $name]);
        }

        $roles['Administrator']->permissions()->attach(Permission::all());

        $roles['Direktur']->permissions()->attach(Permission::all());

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

        $roles['Finance']->permissions()->attach($financeViewCreate);

        $businessFull = Permission::query()
            ->whereIn('name', collect($businessEntities)
                ->flatMap(fn (string $entity) => array_map(fn (string $action) => "{$action}_{$entity}", $businessActions))
                ->all())
            ->get();

        foreach (['General Manager', 'HR Manager', 'Sales', 'Technician'] as $roleName) {
            $roles[$roleName]->permissions()->attach($businessFull);
        }

        $sopView = Permission::where('name', 'view_sops')->first();
        foreach (['General Manager', 'HR Manager', 'Finance', 'Sales', 'Technician'] as $roleName) {
            if ($sopView) {
                $roles[$roleName]->permissions()->attach($sopView->id);
            }
        }

        $admin = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@cendana.com',
        ]);

        $user = User::factory()->create([
            'name' => 'User Biasa',
            'email' => 'user@cendana.com',
        ]);

        $tunai = Wallet::create(['name' => 'Tunai', 'balance' => 0]);
        $bri = Wallet::create(['name' => 'BRI', 'balance' => 0]);
        $bca = Wallet::create(['name' => 'BCA', 'balance' => 0]);
        $gopay = Wallet::create(['name' => 'GoPay', 'balance' => 0]);

        $coas = [];
        $coaData = [
            ['code' => '4-1000', 'name' => 'Pendapatan Jasa', 'type' => 'income', 'category' => 'pemasukan'],
            ['code' => '4-2000', 'name' => 'Pendapatan Lain', 'type' => 'income', 'category' => 'pemasukan'],
            ['code' => '5-1000', 'name' => 'Beban Gaji', 'type' => 'expense', 'category' => 'pengeluaran'],
            ['code' => '5-2000', 'name' => 'Beban Operasional', 'type' => 'expense', 'category' => 'pengeluaran'],
            ['code' => '5-3000', 'name' => 'Beban Transport', 'type' => 'expense', 'category' => 'pengeluaran'],
            ['code' => '5-4000', 'name' => 'Beban Makanan', 'type' => 'expense', 'category' => 'pengeluaran'],
            ['code' => '5-5000', 'name' => 'Beban Listrik & Internet', 'type' => 'expense', 'category' => 'pengeluaran'],
            ['code' => '5-6000', 'name' => 'Pajak', 'type' => 'tax', 'category' => 'pengeluaran'],
            ['code' => '1-1000', 'name' => 'Kas', 'type' => 'asset', 'category' => null],
            ['code' => '1-2000', 'name' => 'Transfer Antar Dompet', 'type' => 'asset', 'category' => 'transfer'],
            ['code' => '2-1000', 'name' => 'Utang Usaha', 'type' => 'liability', 'category' => null],
        ];
        foreach ($coaData as $c) {
            $coas[$c['code']] = Coa::create($c);
        }

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
                    'coa_id' => $coas['4-1000']->id,
                    'amount' => rand(15, 50) * 100000,
                    'transaction_date' => $date->format('Y-m-d'),
                ];
            }

            $transactions[] = [
                'name' => 'Makan Siang',
                'user_id' => $admin->id,
                'wallet_id' => $tunai->id,
                'coa_id' => $coas['5-4000']->id,
                'amount' => rand(15, 50) * 1000,
                'transaction_date' => $date->format('Y-m-d'),
            ];

            if (rand(0, 1)) {
                $transactions[] = [
                    'name' => 'Transportasi',
                    'user_id' => $admin->id,
                    'wallet_id' => $gopay->id,
                    'coa_id' => $coas['5-3000']->id,
                    'amount' => rand(20, 100) * 1000,
                    'transaction_date' => $date->format('Y-m-d'),
                ];
            }

            if ($day % 7 === 0) {
                $transactions[] = [
                    'name' => 'Belanja Bulanan',
                    'user_id' => $admin->id,
                    'wallet_id' => $gopay->id,
                    'coa_id' => $coas['5-2000']->id,
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
                    'coa_id' => $coas['1-2000']->id,
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
