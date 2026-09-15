<?php

namespace Tests\Feature;

use App\Models\Coa;
use App\Models\PelangganCorporate;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleMarketingCostTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $wallet;

    protected Coa $incomeCoa;

    protected Coa $expenseCoa;

    protected PelangganCorporate $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $this->wallet = Wallet::create(['name' => 'Tunai', 'balance' => 0]);

        $this->incomeCoa = Coa::create([
            'code' => '40100',
            'name' => 'Pendapatan',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);

        $this->expenseCoa = Coa::create([
            'code' => '60410',
            'name' => 'Marketing Freelance',
            'type' => 'expense',
            'category' => 'pengeluaran',
            'is_active' => true,
        ]);

        $this->customer = PelangganCorporate::create([
            'customer_code' => 'CUST-TEST',
            'name' => 'PT Uji Coba',
            'address' => 'Jl. Test 1',
            'pic' => 'PIC',
            'pic_phone' => '081200000000',
            'contract_start' => now()->subMonths(3),
            'is_subscription' => true,
            'monthly_fee' => 1000000,
            'due_day' => 5,
            'is_active' => true,
        ]);

        Setting::set('coa_marketing_id', $this->expenseCoa->id);
        Setting::set('wallet_penjualan_id', $this->wallet->id);
    }

    protected function makeSale(float $total = 1000000, float $marketingCost = 0): Sale
    {
        return Sale::create([
            'invoice_no' => 'SJ-'.now()->format('Ym').'-'.$this->user->id.'-'.random_int(100, 999),
            'date' => now(),
            'due_date' => now()->addDays(30),
            'customer_id' => $this->customer->id,
            'coa_id' => $this->incomeCoa->id,
            'wallet_id' => $this->wallet->id,
            'total' => $total,
            'marketing_cost' => $marketingCost,
            'status' => 'berjalan',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_sale_with_marketing_cost_creates_expense_transaction(): void
    {
        $sale = $this->makeSale(total: 1000000, marketingCost: 150000);

        $tx = Transaction::query()->where('marketing_for_sale_id', $sale->id)->first();

        $this->assertNotNull($tx);
        $this->assertEquals(150000, (float) $tx->amount);
        $this->assertEquals($this->expenseCoa->id, $tx->coa_id);
        $this->assertEquals($this->wallet->id, $tx->wallet_id);
        $this->assertNull($tx->sale_id);

        $this->assertTrue(str_contains((string) $tx->description, 'Biaya Marketing'));
    }

    public function test_adding_marketing_cost_to_paid_invoice_updates_expense_and_keeps_lunas(): void
    {
        $sale = $this->makeSale(total: 1000000);
        $this->assertNull(Transaction::query()->where('marketing_for_sale_id', $sale->id)->first());

        Transaction::create([
            'name' => $sale->invoice_no,
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'coa_id' => $this->incomeCoa->id,
            'amount' => 1000000,
            'transaction_date' => now(),
            'sale_id' => $sale->id,
        ]);

        $sale->refresh();
        $this->assertEquals('lunas', $sale->status);
        $this->assertEquals(1000000, (float) $sale->total_paid);

        $sale->update(['marketing_cost' => 200000]);

        $tx = Transaction::query()->where('marketing_for_sale_id', $sale->id)->first();
        $this->assertNotNull($tx);
        $this->assertEquals(200000, (float) $tx->amount);
        $this->assertNull($tx->sale_id);

        $sale->refresh();
        $this->assertEquals('lunas', $sale->status);
        $this->assertEquals(1000000, (float) $sale->total_paid);
    }

    public function test_setting_marketing_cost_to_zero_deletes_expense_transaction(): void
    {
        $sale = $this->makeSale(total: 1000000, marketingCost: 150000);

        $this->assertNotNull(Transaction::query()->where('marketing_for_sale_id', $sale->id)->first());

        $sale->update(['marketing_cost' => 0]);

        $this->assertNull(Transaction::query()->where('marketing_for_sale_id', $sale->id)->first());
    }

    public function test_deleting_sale_deletes_marketing_expense(): void
    {
        $sale = $this->makeSale(total: 1000000, marketingCost: 120000);

        $this->assertNotNull(Transaction::query()->where('marketing_for_sale_id', $sale->id)->first());

        $sale->delete();

        $this->assertNull(Transaction::query()->where('marketing_for_sale_id', $sale->id)->first());
    }

    public function test_editing_sale_through_unified_query_persists(): void
    {
        $sale = $this->makeSale(total: 1000000);

        $method = new \ReflectionMethod(
            'App\Filament\Resources\Sales\SaleResource',
            'unifiedQuery'
        );

        $record = $method->invoke(null)->where('id', $sale->id)->first();

        $this->assertNotNull($record);
        $this->assertEquals('sales', $record->getTable());
        $this->assertFalse((bool) $record->is_retail);

        $record->notes = 'Diubah dari hasil edit';
        $record->marketing_cost = 150000;
        $record->save();

        $fresh = Sale::find($sale->id);
        $this->assertEquals('Diubah dari hasil edit', $fresh->notes);
        $this->assertEquals(150000, (float) $fresh->marketing_cost);

        $this->assertNotNull(Transaction::query()->where('marketing_for_sale_id', $sale->id)->first());
    }
}