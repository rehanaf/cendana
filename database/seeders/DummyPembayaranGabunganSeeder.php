<?php

namespace Database\Seeders;

use App\Models\Coa;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class DummyPembayaranGabunganSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();
        $coa = Coa::find((int) Setting::get('coa_pembelian_id'))
            ?? Coa::query()->where('category', 'pengeluaran')->first();
        $wallet = Wallet::find((int) Setting::get('wallet_pembelian_id'))
            ?? Wallet::query()->where('is_active', true)->orderBy('id')->first();

        if (! $user || ! $coa || ! $wallet) {
            $this->command?->warn('Seeder membutuhkan minimal 1 user, 1 COA pengeluaran, dan 1 wallet.');

            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $coa, $wallet): void {
            // purge hasil run sebelumnya agar seeder bisa dijalankan ulang tanpa duplikat
            if ($oldVendor = Vendor::query()->where('name', 'PT Sumber Makmur Sentosa')->first()) {
                $oldIds = $oldVendor->purchases()->pluck('id');
                $oldRefIds = Transaction::query()->whereIn('purchase_id', $oldIds)->pluck('transaction_reference_id')->filter();
                Transaction::query()->whereIn('purchase_id', $oldIds)->delete();
                \App\Models\TransactionReference::query()->whereIn('id', $oldRefIds)->delete();
                $oldVendor->purchases()->delete();
            }

            $this->createData($user, $coa, $wallet);
        });
    }

    protected function createData(User $user, Coa $coa, Wallet $wallet): void
    {
        $vendor = Vendor::query()->firstOrCreate(
            ['name' => 'PT Sumber Makmur Sentosa'],
            [
                'address' => 'Jl. Industri Raya No. 88, Surabaya',
                'pic' => 'Hendra Wijaya',
                'pic_phone' => '081234567890',
                'is_active' => true,
            ]
        );

        // 3 nota dibayar gabungan dalam 1 transfer bank (referensi sama)
        $dibayarGabungan = [
            ['total' => 7500000, 'notes' => 'Pembelian kabel fiber optic 2 km', 'daysAgo' => 7],
            ['total' => 3200000, 'notes' => 'Pembelian router OSN ONT 20 unit', 'daysAgo' => 7],
            ['total' => 1850000, 'notes' => 'Pembelian konektor & patch cord', 'daysAgo' => 7],
        ];

        // 2 nota sengaja dibiarkan belum lunas untuk uji tombol Bayar Gabungan
        $belumDibayar = [
            ['total' => 5400000, 'notes' => 'Pembelian access point 15 unit', 'daysAgo' => 3],
            ['total' => 2750000, 'notes' => 'Pembelian rack & kabel manajemen', 'daysAgo' => 1],
        ];

        $counter = (int) Purchase::count() + 1;
        $makeInvoiceNo = function () use (&$counter): string {
            return 'PB-' . now()->format('Ym') . '-' . str_pad((string) $counter++, 3, '0', STR_PAD_LEFT);
        };

        $referenceNo = \App\Models\TransactionReference::generateReferenceNo();

        $newPurchases = collect();

        foreach ($dibayarGabungan as $item) {
            $date = now()->subDays($item['daysAgo']);

            $newPurchases->push(Purchase::query()->create([
                'invoice_no' => $makeInvoiceNo(),
                'date' => $date,
                'due_date' => $date->copy()->addDays(30),
                'vendor_id' => $vendor->id,
                'coa_id' => $coa->id,
                'wallet_id' => $wallet->id,
                'total' => $item['total'],
                'status' => 'berjalan',
                'notes' => $item['notes'],
                'created_by' => $user->id,
            ]));
        }

        $reference = \App\Models\TransactionReference::query()->create([
            'reference_no' => $referenceNo,
            'description' => 'Transfer bank BCA a/n PT Sumber Makmur Sentosa',
            'user_id' => $user->id,
        ]);

        $newPurchases->each(function (Purchase $purchase) use ($reference, $wallet, $coa, $user, $vendor): void {
            Transaction::query()->create([
                'name' => $purchase->invoice_no,
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'coa_id' => $coa->id,
                'amount' => $purchase->total,
                'description' => 'Pembayaran ' . $purchase->invoice_no . ' - ' . $vendor->name,
                'transaction_reference_id' => $reference->id,
                'transaction_date' => $purchase->date,
                'purchase_id' => $purchase->id,
            ]);
        });

        foreach ($belumDibayar as $item) {
            $date = now()->subDays($item['daysAgo']);

            Purchase::query()->create([
                'invoice_no' => $makeInvoiceNo(),
                'date' => $date,
                'due_date' => $date->copy()->addDays(30),
                'vendor_id' => $vendor->id,
                'coa_id' => $coa->id,
                'wallet_id' => $wallet->id,
                'total' => $item['total'],
                'status' => 'berjalan',
                'notes' => $item['notes'],
                'created_by' => $user->id,
            ]);
        }

        $this->command?->info('Dummy pembayaran gabungan dibuat.');
        $this->command?->info('3 nota LUNAS dengan referensi bersama: ' . $referenceNo);
        $this->command?->info('2 nota BELUM lunas (untuk uji tombol Bayar Gabungan).');
    }
}
