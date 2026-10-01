<?php

namespace Tests\Feature;

use App\Filament\Resources\Sales\Pages\ManageSales;
use App\Models\Coa;
use App\Models\InvoiceTemplate;
use App\Models\PelangganCorporate;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CetakInvoiceTotalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $wallet;

    protected Coa $incomeCoa;

    protected PelangganCorporate $customer;

    protected InvoiceTemplate $template;

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

        $this->customer = PelangganCorporate::create([
            'customer_code' => 'K001',
            'name' => 'PT Uji Coba',
            'is_subscription' => true,
            'monthly_fee' => 1000000,
            'due_day' => 1,
            'is_active' => true,
        ]);

        $this->template = InvoiceTemplate::create([
            'name' => 'Default',
            'content' => 'x',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    protected function makeSale(float $total = 1000000): Sale
    {
        return Sale::create([
            'invoice_no' => 'SJ-'.random_int(1000, 9999),
            'date' => now(),
            'due_date' => now()->addDays(30),
            'customer_id' => $this->customer->id,
            'coa_id' => $this->incomeCoa->id,
            'wallet_id' => $this->wallet->id,
            'total' => $total,
            'marketing_cost' => 0,
            'status' => 'berjalan',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_print_modal_can_edit_total_to_partial_amount(): void
    {
        $sale = $this->makeSale(1000000);

        Livewire::test(ManageSales::class)
            ->callTableAction('cetak-invoice', $sale->id, [
                'template' => $this->template->id,
                'keterangan' => 'Tagihan sebagian',
                'total' => 400000,
            ]);

        $sale->refresh();

        $this->assertEquals(400000, (float) $sale->total);
        $this->assertEquals('Tagihan sebagian', $sale->notes);
        $this->assertEquals(400000, (float) $sale->sisa);
    }

    public function test_print_modal_rejects_total_below_paid_amount(): void
    {
        $sale = $this->makeSale(1000000);

        Transaction::create([
            'name' => $sale->invoice_no,
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'coa_id' => $this->incomeCoa->id,
            'amount' => 600000,
            'transaction_date' => now()->toDateString(),
            'sale_id' => $sale->id,
        ]);

        Livewire::test(ManageSales::class)
            ->callTableAction('cetak-invoice', $sale->id, [
                'template' => $this->template->id,
                'total' => 100000,
            ])
            ->assertNotified('Total tidak boleh lebih kecil dari yang sudah dibayar');

        $this->assertEquals(1000000, (float) $sale->fresh()->total);
    }

    public function test_print_modal_refreshes_status_when_total_increases(): void
    {
        $sale = $this->makeSale(1000000);

        Transaction::create([
            'name' => $sale->invoice_no,
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'coa_id' => $this->incomeCoa->id,
            'amount' => 1000000,
            'transaction_date' => now()->toDateString(),
            'sale_id' => $sale->id,
        ]);

        $this->assertSame('lunas', $sale->fresh()->status);

        Livewire::test(ManageSales::class)
            ->callTableAction('cetak-invoice', $sale->id, [
                'template' => $this->template->id,
                'total' => 1500000,
            ]);

        $sale->refresh();

        $this->assertEquals(1500000, (float) $sale->total);
        $this->assertSame('berjalan', $sale->status);
        $this->assertEquals(500000, (float) $sale->sisa);
    }
}
