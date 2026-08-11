<?php

namespace Tests\Feature;

use App\Filament\Resources\Purchases\Pages\ManagePurchases;
use App\Filament\Resources\Sales\Pages\ManageSales;
use App\Filament\Resources\SubscriptionInvoices\Pages\ManageSubscriptionInvoices;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class CreateFormSmokeTest extends TestCase
{
    public function test_create_forms_mount_and_show_payment_fields(): void
    {
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Membutuhkan koneksi MySQL.');
        }

        $user = User::query()->first();
        $this->actingAs($user);

        foreach ([ManageSales::class, ManagePurchases::class, ManageSubscriptionInvoices::class] as $page) {
            Livewire::test($page)
                ->mountAction('create')
                ->assertOk()
                ->assertFormFieldExists('pay_now')
                ->assertFormFieldExists('wallet_id')
                ->assertFormFieldExists('coa_id');
        }
    }

    public function test_sale_pay_now_creates_transaction_with_chosen_wallet_and_coa(): void
    {
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Membutuhkan koneksi MySQL.');
        }

        $user = User::query()->first();
        $this->actingAs($user);

        $customer = \App\Models\PelangganCorporate::query()->first();
        $wallet = \App\Models\Wallet::query()->first();
        $coa = \App\Models\Coa::query()->where('category', 'pemasukan')->first();

        if (! $customer || ! $wallet || ! $coa) {
            $this->markTestSkipped('Data dasar tidak tersedia');
        }

        $invoiceNo = 'TEST-' . now()->format('YmdHis');

        try {
            Livewire::test(ManageSales::class)
                ->callAction('create', data: [
                    'invoice_no' => $invoiceNo,
                    'customer_id' => $customer->id,
                    'date' => now()->format('Y-m-d'),
                    'due_date' => now()->format('Y-m-d'),
                    'total' => 500000,
                    'pay_now' => true,
                    'wallet_id' => $wallet->id,
                    'coa_id' => $coa->id,
                    'marketing_cost' => 0,
                ]);

            $sale = \App\Models\Sale::query()->where('invoice_no', $invoiceNo)->first();
            $this->assertNotNull($sale);
            $this->assertSame((int) $wallet->id, (int) $sale->wallet_id);
            $this->assertSame((int) $coa->id, (int) $sale->coa_id);
            $this->assertSame(1, $sale->journalTransactions()->count());
            $this->assertEqualsWithDelta(500000, (float) $sale->journalTransactions()->sum('amount'), 0.01);
            $this->assertSame('lunas', $sale->status);
        } finally {
            \App\Models\Sale::query()->where('invoice_no', $invoiceNo)->get()->each->delete();
        }
    }
}
