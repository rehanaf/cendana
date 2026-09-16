<?php

namespace Tests\Feature;

use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Coa;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\TransactionReference;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionWalletTabSaldoTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

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
    }

    public function test_wallet_tab_query_filters_asal_and_tujuan_and_computes_saldo(): void
    {
        $walletA = Wallet::create(['name' => 'Tunai', 'balance' => 0]);
        $walletB = Wallet::create(['name' => 'Bank', 'balance' => 0]);

        $incomeCoa = Coa::create([
            'code' => '4-1000',
            'name' => 'Pendapatan',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);
        $expenseCoa = Coa::create([
            'code' => '5-1000',
            'name' => 'Beban',
            'type' => 'expense',
            'category' => 'pengeluaran',
            'is_active' => true,
        ]);
        $transferCoa = Coa::create([
            'code' => '1-2000',
            'name' => 'Transfer Antar Dompet',
            'type' => 'asset',
            'category' => 'transfer',
            'is_active' => true,
        ]);

        $topUp = Transaction::create([
            'name' => 'Top Up',
            'user_id' => $this->user->id,
            'wallet_id' => $walletA->id,
            'coa_id' => $incomeCoa->id,
            'amount' => 500000,
            'transaction_date' => '2026-01-01',
        ]);

        $transfer = Transaction::create([
            'name' => 'Transfer Internal',
            'user_id' => $this->user->id,
            'wallet_id' => $walletA->id,
            'to_wallet_id' => $walletB->id,
            'coa_id' => $transferCoa->id,
            'amount' => 200000,
            'transaction_date' => '2026-01-02',
        ]);

        $expense = Transaction::create([
            'name' => 'Belanja',
            'user_id' => $this->user->id,
            'wallet_id' => $walletA->id,
            'coa_id' => $expenseCoa->id,
            'amount' => 50000,
            'transaction_date' => '2026-01-03',
        ]);

        $livewire = new class extends \Livewire\Component
        {
            public array $tableFilters = [];
        };

        $method = new \ReflectionMethod(TransactionsTable::class, 'applyQueryFilters');
        $filtered = $method->invoke(null, Transaction::query(), $livewire);

        $saldoA = TransactionsTable::applyWalletTabQuery($filtered, $walletA)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => (float) $row->saldo]);

        $this->assertCount(3, $saldoA);
        $this->assertEquals(500000, $saldoA[$topUp->id]);
        $this->assertEquals(300000, $saldoA[$transfer->id]);
        $this->assertEquals(250000, $saldoA[$expense->id]);

        // Dompet tujuan muncul di tab dompet asal/tujuan filter (transfer masuk).
        $saldoB = TransactionsTable::applyWalletTabQuery($filtered, $walletB)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => (float) $row->saldo]);

        $this->assertCount(1, $saldoB);
        $this->assertEquals(200000, $saldoB[$transfer->id]);
    }

    public function test_wallet_tab_saldo_works_with_reference_grouping_union(): void
    {
        $walletA = Wallet::create(['name' => 'Kas', 'balance' => 0]);

        $incomeCoa = Coa::create([
            'code' => '4-1000',
            'name' => 'Pendapatan',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);
        $expenseCoa = Coa::create([
            'code' => '5-1000',
            'name' => 'Beban',
            'type' => 'expense',
            'category' => 'pengeluaran',
            'is_active' => true,
        ]);

        $reference = TransactionReference::create([
            'reference_no' => 'REF-TEST-001',
            'description' => 'Referensi gabungan',
            'user_id' => $this->user->id,
        ]);

        $refIncome = Transaction::create([
            'name' => 'Pemasukan Gabung',
            'user_id' => $this->user->id,
            'wallet_id' => $walletA->id,
            'coa_id' => $incomeCoa->id,
            'amount' => 500000,
            'transaction_date' => '2026-02-01',
            'transaction_reference_id' => $reference->id,
        ]);

        $refExpense = Transaction::create([
            'name' => 'Pengeluaran Gabung',
            'user_id' => $this->user->id,
            'wallet_id' => $walletA->id,
            'coa_id' => $expenseCoa->id,
            'amount' => 200000,
            'transaction_date' => '2026-02-02',
            'transaction_reference_id' => $reference->id,
        ]);

        $plainIncome = Transaction::create([
            'name' => 'Pemasukan Manual',
            'user_id' => $this->user->id,
            'wallet_id' => $walletA->id,
            'coa_id' => $incomeCoa->id,
            'amount' => 150000,
            'transaction_date' => '2026-02-03',
        ]);

        $livewire = new class extends \Livewire\Component
        {
            public array $tableFilters = ['tampilkanReferensi' => ['isActive' => true]];
        };

        $method = new \ReflectionMethod(TransactionsTable::class, 'applyQueryFilters');
        $unionQuery = $method->invoke(null, Transaction::query(), $livewire);

        $rows = TransactionsTable::applyWalletTabQuery($unionQuery, $walletA)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => (float) $row->saldo]);

        // Baris referensi: +500.000 - 200.000 = +300.000; lalu baris manual +150.000.
        $this->assertCount(2, $rows);
        $this->assertEquals(300000, (float) $rows[-$refIncome->id]);
        $this->assertEquals(450000, (float) $rows[$plainIncome->id]);
    }

    public function test_wallet_tab_saldo_works_on_manual_only_path(): void
    {
        $walletA = Wallet::create(['name' => 'Kas Uang', 'balance' => 0]);

        $incomeCoa = Coa::create([
            'code' => '4-1000',
            'name' => 'Pendapatan',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);

        $income = Transaction::create([
            'name' => 'Pemasukan Manual',
            'user_id' => $this->user->id,
            'wallet_id' => $walletA->id,
            'coa_id' => $incomeCoa->id,
            'amount' => 100000,
            'transaction_date' => '2026-03-01',
        ]);

        $manualQuery = Transaction::query()
            ->whereNull('sale_id')
            ->whereNull('purchase_id')
            ->whereNull('subscription_invoice_id')
            ->whereNull('retail_invoice_id')
            ->whereNull('transaction_reference_id')
            ->select([
                DB::raw('transactions.*'),
                DB::raw('0 as is_ref'),
                DB::raw('NULL as category'),
                DB::raw('NULL as ref_net'),
            ]);

        $rows = TransactionsTable::applyWalletTabQuery($manualQuery, $walletA)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => (float) $row->saldo]);

        $this->assertCount(1, $rows);
        $this->assertEquals(100000, $rows[$income->id]);
    }

    public function test_transaction_resource_wallet_tab_shows_saldo_in_total_column(): void
    {
        $wallet = Wallet::create(['name' => 'Tunai', 'balance' => 0]);

        $incomeCoa = Coa::create([
            'code' => '4-1000',
            'name' => 'Pendapatan',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);

        Transaction::create([
            'name' => 'Top Up',
            'user_id' => $this->user->id,
            'wallet_id' => $wallet->id,
            'coa_id' => $incomeCoa->id,
            'amount' => 100000,
            'transaction_date' => '2026-04-01',
        ]);

        $this->actingAs($this->user);

        Livewire::test(ListTransactions::class)
            ->assertOk()
            ->set('activeTab', 'wallet_'.$wallet->id)
            ->assertOk()
            ->assertSee('100.000');
    }
}