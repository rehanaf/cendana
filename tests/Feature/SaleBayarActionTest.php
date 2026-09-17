<?php

namespace Tests\Feature;

use App\Filament\Resources\Sales\Pages\ManageSales;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Coa;
use App\Models\PelangganCorporate;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaleBayarActionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $wallet;

    protected Coa $incomeCoa;

    protected PelangganCorporate $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Administrator']);
        $this->user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => 'password',
            'role_id' => $role->id,
        ]);

        $this->wallet = Wallet::create(['name' => 'Tunai', 'balance' => 0]);

        $this->incomeCoa = Coa::create([
            'code' => '40100',
            'name' => 'Pendapatan',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);

        Coa::create([
            'code' => '60410',
            'name' => 'Marketing Freelance',
            'type' => 'expense',
            'category' => 'pengeluaran',
            'is_active' => true,
        ]);

        $this->customer = PelangganCorporate::create([
            'customer_code' => 'K001',
            'name' => 'PT Uji Coba',
            'address' => 'Jl. Test 1',
            'pic' => 'PIC',
            'pic_phone' => '081200000000',
            'contract_start' => now()->subMonths(3),
            'is_subscription' => false,
            'monthly_fee' => 0,
            'due_day' => 1,
            'is_active' => true,
        ]);

        Setting::set('coa_penjualan_id', $this->incomeCoa->id);
        Setting::set('wallet_penjualan_id', $this->wallet->id);

        $this->actingAs($this->user);
    }

    protected function makeSale(float $total = 1000000): Sale
    {
        return Sale::create([
            'invoice_no' => 'SJ-'.now()->format('Ym').'-'.random_int(1000, 9999),
            'date' => now(),
            'due_date' => now()->addDays(30),
            'customer_id' => $this->customer->id,
            'coa_id' => $this->incomeCoa->id,
            'wallet_id' => $this->wallet->id,
            'total' => $total,
            'marketing_cost' => 0,
            'status' => 'berjalan',
            'notes' => null,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_unified_query_resolves_row_by_id_column(): void
    {
        $sale = $this->makeSale();

        $row = SaleResource::unifiedQuery()->where('id', $sale->id)->first();

        $this->assertNotNull($row);
        $this->assertEquals($sale->id, $row->id);
        $this->assertEquals($sale->id, $row->sale_id);
        $this->assertEquals(0, (int) $row->is_retail);
    }

    public function test_bayar_row_action_mount_resolves_record_without_qualified_key_error(): void
    {
        $sale = $this->makeSale();

        Livewire::test(ManageSales::class)
            ->callTableAction('bayar', $sale->id)
            ->assertOk();
    }

    public function test_bayar_gabungan_bulk_action_resolves_selected_records(): void
    {
        $sale = $this->makeSale();

        Livewire::test(ManageSales::class)
            ->selectTableRecords([(string) $sale->id])
            ->callTableBulkAction('bayar-gabungan', [
                'coa_id' => $this->incomeCoa->id,
                'wallet_id' => $this->wallet->id,
                'transaction_date' => now()->toDateString(),
            ])
            ->assertOk();

        $this->assertNotNull(Transaction::query()->where('sale_id', $sale->id)->first());
        $this->assertSame('lunas', $sale->fresh()->status);
    }
}
