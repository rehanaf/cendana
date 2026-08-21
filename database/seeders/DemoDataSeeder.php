<?php

namespace Database\Seeders;

use App\Models\Coa;
use App\Models\PelangganCorporate;
use App\Models\PelangganRetail;
use App\Models\RetailInvoice;
use App\Models\Sale;
use App\Models\SubscriptionInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        Transaction::query()->whereNotNull('sale_id')
            ->orWhereNotNull('subscription_invoice_id')
            ->orWhereNotNull('retail_invoice_id')
            ->delete();

        RetailInvoice::query()->delete();
        SubscriptionInvoice::query()->delete();
        Sale::query()->delete();
        PelangganRetail::query()->delete();
        PelangganCorporate::query()->delete();

        $user = User::query()->first();
        $coa = Coa::query()->where('category', 'pemasukan')->first() ?? Coa::query()->first();
        $wallet = Wallet::query()->orderBy('id')->first();

        if (! $user || ! $coa || ! $wallet) {
            $this->command?->warn('Seeder membutuhkan minimal 1 user, 1 COA pemasukan, dan 1 wallet.');

            return;
        }

        $corporateNames = [
            'PT Cendana Teknologi',
            'PT Nusantara Jaya',
            'CV Karya Abadi',
            'PT Samudra Logistik',
            'RS Harapan Sehat',
        ];

        $corporateCustomers = [];
        foreach ($corporateNames as $index => $name) {
            $corporateCustomers[] = PelangganCorporate::query()->create([
                'customer_code' => 'CUST-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'address' => 'Jl. Contoh No. '.($index + 1),
                'pic' => 'PIC '.$name,
                'pic_phone' => '0812'.str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'contract_start' => now()->subMonths(12),
                'is_subscription' => true,
                'monthly_fee' => [1500000, 2500000, 3500000][$index % 3],
                'due_day' => 5,
                'is_active' => true,
            ]);
        }

        $retailNames = [
            'Budi Santoso',
            'Siti Rahayu',
            'Agus Prasetyo',
            'Dewi Lestari',
            'Rudi Hartono',
        ];

        $retailCustomers = [];
        foreach ($retailNames as $index => $name) {
            $retailCustomers[] = PelangganRetail::query()->create([
                'customer_code' => 'RT-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)).'@mail.com',
                'subscription_start_date' => now()->subMonths(6),
                'internet_package_id' => ($index % 5) + 1,
                'block_location' => 'Blok A No. '.($index + 1),
                'full_address' => 'Perumahan Contoh Blok A No. '.($index + 1),
                'wa' => '0813'.str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'is_active' => true,
            ]);
        }

        $invoiceCounter = 1;

        foreach ($corporateCustomers as $index => $customer) {
            foreach (range(-6, 0) as $monthOffset) {
                $date = now()->addMonths($monthOffset)->startOfMonth()->addDays(min(5, $monthOffset + 6));
                $total = $customer->monthly_fee ?: 1500000;

                $invoice = SubscriptionInvoice::query()->create([
                    'invoice_no' => 'SUB-'.now()->addMonths($monthOffset)->format('Ym').'-'.str_pad((string) $invoiceCounter++, 3, '0', STR_PAD_LEFT),
                    'customer_id' => $customer->id,
                    'period' => $date->copy()->startOfMonth(),
                    'date' => $date,
                    'due_date' => $date->copy()->addDays(15),
                    'coa_id' => $coa->id,
                    'wallet_id' => $wallet->id,
                    'total' => $total,
                    'status' => 'berjalan',
                    'created_by' => $user->id,
                ]);

                if ($monthOffset >= -3) {
                    Transaction::query()->create([
                        'name' => $invoice->invoice_no,
                        'user_id' => $user->id,
                        'wallet_id' => $wallet->id,
                        'coa_id' => $coa->id,
                        'amount' => $total,
                        'description' => 'Pembayaran '.$invoice->invoice_no.' - '.$customer->name,
                        'transaction_date' => $date,
                        'subscription_invoice_id' => $invoice->id,
                    ]);
                }
            }

            foreach (range(-5, 0) as $monthOffset) {
                if ($index === 3 && $monthOffset === 0) {
                    continue;
                }

                $date = now()->addMonths($monthOffset)->startOfMonth()->addDays($monthOffset + 12);
                $total = [5000000, 8000000, 12000000][$index % 3] + ((abs($monthOffset) - 1) * 500000);

                $sale = Sale::query()->create([
                    'invoice_no' => 'SJ-'.now()->addMonths($monthOffset)->format('Ym').'-'.str_pad((string) $invoiceCounter++, 3, '0', STR_PAD_LEFT),
                    'date' => $date,
                    'due_date' => $date->copy()->addDays(30),
                    'customer_id' => $customer->id,
                    'coa_id' => $coa->id,
                    'wallet_id' => $wallet->id,
                    'total' => $total,
                    'marketing_cost' => 0,
                    'status' => 'berjalan',
                    'created_by' => $user->id,
                ]);

                if ($monthOffset >= -2) {
                    Transaction::query()->create([
                        'name' => $sale->invoice_no,
                        'user_id' => $user->id,
                        'wallet_id' => $wallet->id,
                        'coa_id' => $coa->id,
                        'amount' => (int) ($total * 0.6),
                        'description' => 'Pembayaran '.$sale->invoice_no.' - '.$customer->name,
                        'transaction_date' => $date->copy()->addDays(5),
                        'sale_id' => $sale->id,
                    ]);
                }
            }
        }

        foreach ($retailCustomers as $index => $customer) {
            foreach (range(-6, 0) as $monthOffset) {
                $date = now()->addMonths($monthOffset)->startOfMonth()->addDays(10);
                $total = (float) ($customer->paketInternet?->price ?? 150000);

                $invoice = RetailInvoice::query()->create([
                    'invoice_no' => 'RT-'.now()->addMonths($monthOffset)->format('Ym').'-'.str_pad((string) $invoiceCounter++, 3, '0', STR_PAD_LEFT),
                    'retail_customer_id' => $customer->id,
                    'internet_package_id' => $customer->internet_package_id,
                    'period' => $date->copy()->startOfMonth(),
                    'date' => $date,
                    'due_date' => $date->copy()->addDays(15),
                    'coa_id' => $coa->id,
                    'wallet_id' => $wallet->id,
                    'total' => $total,
                    'status' => 'berjalan',
                    'created_by' => $user->id,
                ]);

                if ($monthOffset >= -4) {
                    Transaction::query()->create([
                        'name' => $invoice->invoice_no,
                        'user_id' => $user->id,
                        'wallet_id' => $wallet->id,
                        'coa_id' => $coa->id,
                        'amount' => $total,
                        'description' => 'Pembayaran '.$invoice->invoice_no.' - '.$customer->name,
                        'transaction_date' => $date,
                        'retail_invoice_id' => $invoice->id,
                    ]);
                }
            }
        }

        $this->command?->info('Dummy data penjualan berhasil dibuat.');
    }
}
