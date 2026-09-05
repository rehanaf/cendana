<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Models\Coa;
use App\Models\PelangganCorporate;
use App\Models\PelangganRetail;
use App\Models\Purchase;
use App\Models\RetailInvoice;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SubscriptionInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteEmptyInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Wallet $wallet;
    protected Coa $coa;
    protected PelangganCorporate $corporate;
    protected PelangganRetail $retail;

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

        $this->wallet = Wallet::create(['name' => 'Kas', 'balance' => 0]);
        $this->coa = Coa::create([
            'code' => '4-1000',
            'name' => 'Pendapatan',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);
        $this->corporate = PelangganCorporate::create(['name' => 'PT ABC', 'customer_code' => 'C001']);
        $this->retail = PelangganRetail::create(['name' => 'Pelanggan Retail', 'customer_code' => 'R001']);
    }

    public function test_hapus_invoice_bulan_agustus_yang_belum_ada_transaksi(): void
    {
        $august = now()->setDate(2025, 8, 15)->format('Y-m-d');

        $sale = Sale::create(['invoice_no' => 'S-001', 'date' => $august, 'due_date' => $august, 'customer_id' => $this->corporate->id, 'coa_id' => $this->coa->id, 'wallet_id' => $this->wallet->id, 'total' => 100000, 'status' => 'berjalan', 'created_by' => $this->user->id]);
        $purchase = Purchase::create(['invoice_no' => 'P-001', 'date' => $august, 'due_date' => $august, 'vendor_id' => Vendor::create(['name' => 'PT Suplier'])->id, 'coa_id' => $this->coa->id, 'wallet_id' => $this->wallet->id, 'total' => 50000, 'status' => 'berjalan', 'created_by' => $this->user->id]);
        $subscription = SubscriptionInvoice::create(['invoice_no' => 'B-001', 'customer_id' => $this->corporate->id, 'period' => $august, 'date' => $august, 'due_date' => $august, 'coa_id' => $this->coa->id, 'wallet_id' => $this->wallet->id, 'total' => 200000, 'status' => 'berjalan', 'created_by' => $this->user->id]);
        $retail = RetailInvoice::create(['invoice_no' => 'R-001', 'retail_customer_id' => $this->retail->id, 'period' => $august, 'date' => $august, 'due_date' => $august, 'coa_id' => $this->coa->id, 'wallet_id' => $this->wallet->id, 'total' => 75000, 'status' => 'berjalan', 'created_by' => $this->user->id]);

        $counts = Settings::deleteEmptyInvoices(2025, 8);

        $this->assertSame(1, $counts['penjualan']);
        $this->assertSame(1, $counts['pembelian']);
        $this->assertSame(1, $counts['langganan']);
        $this->assertSame(1, $counts['retail']);

        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id]);
        $this->assertDatabaseMissing('subscription_invoices', ['id' => $subscription->id]);
        $this->assertDatabaseMissing('retail_invoices', ['id' => $retail->id]);

        $this->assertEquals(0, (float) $this->wallet->fresh()->balance);
    }

    public function test_invoice_yang_memiliki_transaksi_tidak_dihapus(): void
    {
        $august = now()->setDate(2025, 8, 15)->format('Y-m-d');

        $sale = Sale::create(['invoice_no' => 'S-001', 'date' => $august, 'due_date' => $august, 'customer_id' => $this->corporate->id, 'coa_id' => $this->coa->id, 'wallet_id' => $this->wallet->id, 'total' => 100000, 'status' => 'berjalan', 'created_by' => $this->user->id]);

        Transaction::create([
            'name' => 'Bayar',
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'coa_id' => $this->coa->id,
            'amount' => 100000,
            'transaction_date' => $august,
            'sale_id' => $sale->id,
        ]);

        $counts = Settings::deleteEmptyInvoices(2025, 8);

        $this->assertSame(0, $counts['penjualan']);
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertEquals(100000, (float) $this->wallet->fresh()->balance);
    }

    public function test_invoice_bulan_lain_tidak_terpengaruh(): void
    {
        $cop = PelangganCorporate::create(['name' => 'PT XYZ', 'customer_code' => 'C002']);
        $july = now()->setDate(2025, 7, 15)->format('Y-m-d');
        $sale = Sale::create(['invoice_no' => 'S-002', 'date' => $july, 'due_date' => $july, 'customer_id' => $cop->id, 'coa_id' => $this->coa->id, 'wallet_id' => $this->wallet->id, 'total' => 100000, 'status' => 'berjalan', 'created_by' => $this->user->id]);

        $counts = Settings::deleteEmptyInvoices(2025, 8);

        $this->assertSame(0, $counts['penjualan']);
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
    }
}