<?php

namespace Tests\Feature;

use App\Models\Coa;
use App\Models\PaketInternet;
use App\Models\PelangganCorporate;
use App\Models\PelangganRetail;
use App\Models\RetailInvoice;
use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\RetailInvoiceService;
use App\Services\SubscriptionInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionMarketingCostTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $wallet;

    protected Coa $incomeCoa;

    protected Coa $expenseCoa;

    protected PelangganCorporate $corporateCustomer;

    protected PelangganRetail $retailCustomer;

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

        $package = PaketInternet::create([
            'name' => 'Paket 10 Mbps',
            'price' => 150000,
            'is_active' => true,
        ]);

        $this->corporateCustomer = PelangganCorporate::create([
            'customer_code' => 'CUST-TEST',
            'name' => 'PT Uji Coba',
            'address' => 'Jl. Test 1',
            'pic' => 'PIC',
            'pic_phone' => '081200000000',
            'contract_start' => now()->subMonths(3),
            'is_subscription' => true,
            'monthly_fee' => 1000000,
            'marketing_cost' => 150000,
            'due_day' => 5,
            'is_active' => true,
        ]);

        $this->retailCustomer = PelangganRetail::create([
            'customer_code' => 'RETAIL-TEST',
            'name' => 'Budi Santoso',
            'email' => 'budi@test.com',
            'subscription_start_date' => now()->subMonths(2),
            'internet_package_id' => $package->id,
            'marketing_cost' => 50000,
            'is_active' => true,
        ]);

        Setting::set('coa_marketing_id', $this->expenseCoa->id);
        Setting::set('wallet_langganan_id', $this->wallet->id);
        Setting::set('wallet_retail_id', $this->wallet->id);
    }

    protected function makeSubscriptionInvoice(float $total = 1000000): SubscriptionInvoice
    {
        return SubscriptionInvoice::create([
            'invoice_no' => 'SUB-'.now()->format('Ym').'-'.random_int(1000, 9999),
            'customer_id' => $this->corporateCustomer->id,
            'period' => now()->startOfMonth(),
            'date' => now(),
            'due_date' => now()->addDays(30),
            'coa_id' => $this->incomeCoa->id,
            'wallet_id' => $this->wallet->id,
            'total' => $total,
            'status' => 'berjalan',
            'created_by' => $this->user->id,
        ]);
    }

    protected function makeRetailInvoice(float $total = 150000): RetailInvoice
    {
        return RetailInvoice::create([
            'invoice_no' => 'RTL-'.now()->format('Ym').'-'.random_int(1000, 9999),
            'retail_customer_id' => $this->retailCustomer->id,
            'internet_package_id' => $this->retailCustomer->internet_package_id,
            'period' => now()->startOfMonth(),
            'date' => now(),
            'due_date' => now()->addDays(30),
            'coa_id' => $this->incomeCoa->id,
            'wallet_id' => $this->wallet->id,
            'total' => $total,
            'status' => 'berjalan',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_subscription_invoice_creates_marketing_expense_transaction(): void
    {
        $invoice = $this->makeSubscriptionInvoice();

        $tx = Transaction::query()->where('marketing_for_subscription_invoice_id', $invoice->id)->first();

        $this->assertNotNull($tx);
        $this->assertEquals(150000, (float) $tx->amount);
        $this->assertEquals($this->expenseCoa->id, $tx->coa_id);
        $this->assertEquals($this->wallet->id, $tx->wallet_id);
        $this->assertNull($tx->subscription_invoice_id);
        $this->assertTrue(str_contains((string) $tx->description, 'Biaya Marketing'));
    }

    public function test_retail_invoice_creates_marketing_expense_transaction(): void
    {
        $invoice = $this->makeRetailInvoice();

        $tx = Transaction::query()->where('marketing_for_retail_invoice_id', $invoice->id)->first();

        $this->assertNotNull($tx);
        $this->assertEquals(50000, (float) $tx->amount);
        $this->assertEquals($this->expenseCoa->id, $tx->coa_id);
        $this->assertEquals($this->wallet->id, $tx->wallet_id);
        $this->assertNull($tx->retail_invoice_id);
        $this->assertTrue(str_contains((string) $tx->description, 'Biaya Marketing'));
    }

    public function test_customer_without_marketing_cost_creates_no_expense(): void
    {
        $this->corporateCustomer->update(['marketing_cost' => 0]);
        $this->retailCustomer->update(['marketing_cost' => 0]);

        $sub = $this->makeSubscriptionInvoice();
        $rtl = $this->makeRetailInvoice();

        $this->assertNull(Transaction::query()->where('marketing_for_subscription_invoice_id', $sub->id)->first());
        $this->assertNull(Transaction::query()->where('marketing_for_retail_invoice_id', $rtl->id)->first());
    }

    public function test_marketing_expense_does_not_affect_invoice_total_paid(): void
    {
        $sub = $this->makeSubscriptionInvoice();
        $rtl = $this->makeRetailInvoice();

        $this->assertEquals(0, (float) $sub->total_paid);
        $this->assertEquals(0, (float) $rtl->total_paid);

        Transaction::create([
            'name' => $sub->invoice_no,
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'coa_id' => $this->incomeCoa->id,
            'amount' => 1000000,
            'transaction_date' => now(),
            'subscription_invoice_id' => $sub->id,
        ]);

        Transaction::create([
            'name' => $rtl->invoice_no,
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'coa_id' => $this->incomeCoa->id,
            'amount' => 150000,
            'transaction_date' => now(),
            'retail_invoice_id' => $rtl->id,
        ]);

        $sub->refresh();
        $rtl->refresh();

        $this->assertEquals(1000000, (float) $sub->total_paid);
        $this->assertEquals(150000, (float) $rtl->total_paid);

        $this->assertCount(1, $sub->journalTransactions()->get());
        $this->assertCount(1, $rtl->journalTransactions()->get());
    }

    public function test_deleting_invoice_deletes_marketing_expense(): void
    {
        $sub = $this->makeSubscriptionInvoice();
        $rtl = $this->makeRetailInvoice();

        $this->assertNotNull(Transaction::query()->where('marketing_for_subscription_invoice_id', $sub->id)->first());
        $this->assertNotNull(Transaction::query()->where('marketing_for_retail_invoice_id', $rtl->id)->first());

        $sub->delete();
        $rtl->delete();

        $this->assertNull(Transaction::query()->where('marketing_for_subscription_invoice_id', $sub->id)->first());
        $this->assertNull(Transaction::query()->where('marketing_for_retail_invoice_id', $rtl->id)->first());
    }

    public function test_service_generation_applies_customer_marketing_cost(): void
    {
        $subService = app(SubscriptionInvoiceService::class);
        $result = $subService->generateForPeriod(now()->year, now()->month, $this->corporateCustomer->id);
        $this->assertSame(1, $result['created']);

        $invoice = SubscriptionInvoice::where('customer_id', $this->corporateCustomer->id)->firstOrFail();
        $tx = Transaction::query()->where('marketing_for_subscription_invoice_id', $invoice->id)->first();

        $this->assertNotNull($tx);
        $this->assertEquals(150000, (float) $tx->amount);

        $rtlService = app(RetailInvoiceService::class);
        $result = $rtlService->generateForPeriod(now()->year, now()->month, $this->retailCustomer->id);
        $this->assertSame(1, $result['created']);

        $invoiceRtl = RetailInvoice::where('retail_customer_id', $this->retailCustomer->id)->firstOrFail();
        $txRtl = Transaction::query()->where('marketing_for_retail_invoice_id', $invoiceRtl->id)->first();

        $this->assertNotNull($txRtl);
        $this->assertEquals(50000, (float) $txRtl->amount);
    }

    public function test_invoice_snapshots_marketing_cost_from_customer_at_creation(): void
    {
        $sub = $this->makeSubscriptionInvoice();
        $this->assertEquals(150000, (float) $sub->fresh()->marketing_cost);

        $rtl = $this->makeRetailInvoice();
        $this->assertEquals(50000, (float) $rtl->fresh()->marketing_cost);
    }

    public function test_manual_marketing_cost_overrides_customer_default_on_create(): void
    {
        $sub = SubscriptionInvoice::create([
            'invoice_no' => 'SUB-'.now()->format('Ym').'-'.random_int(1000, 9999),
            'customer_id' => $this->corporateCustomer->id,
            'period' => now()->startOfMonth(),
            'date' => now(),
            'due_date' => now()->addDays(30),
            'coa_id' => $this->incomeCoa->id,
            'wallet_id' => $this->wallet->id,
            'total' => 1000000,
            'marketing_cost' => 99999,
            'status' => 'berjalan',
            'created_by' => $this->user->id,
        ]);

        $tx = Transaction::query()->where('marketing_for_subscription_invoice_id', $sub->id)->first();

        $this->assertNotNull($tx);
        $this->assertEquals(99999, (float) $tx->amount);
        $this->assertEquals(99999, (float) $sub->fresh()->marketing_cost);
    }

    public function test_editing_existing_invoice_marketing_cost_updates_transaction(): void
    {
        $sub = $this->makeSubscriptionInvoice();
        $this->assertEquals(150000, (float) Transaction::query()->where('marketing_for_subscription_invoice_id', $sub->id)->value('amount'));

        $sub->update(['marketing_cost' => 200000]);

        $this->assertEquals(200000, (float) Transaction::query()->where('marketing_for_subscription_invoice_id', $sub->id)->value('amount'));

        $sub->update(['marketing_cost' => 0]);

        $this->assertNull(Transaction::query()->where('marketing_for_subscription_invoice_id', $sub->id)->first());
    }

    public function test_adding_marketing_cost_to_existing_invoice_creates_transaction(): void
    {
        $this->retailCustomer->update(['marketing_cost' => 0]);
        $rtl = $this->makeRetailInvoice();
        $this->assertNull(Transaction::query()->where('marketing_for_retail_invoice_id', $rtl->id)->first());

        $rtl->update(['marketing_cost' => 75000]);

        $this->assertNotNull(Transaction::query()->where('marketing_for_retail_invoice_id', $rtl->id)->first());
        $this->assertEquals(75000, (float) Transaction::query()->where('marketing_for_retail_invoice_id', $rtl->id)->value('amount'));
    }
}
