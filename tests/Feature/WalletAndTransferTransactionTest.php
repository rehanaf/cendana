<?php

namespace Tests\Feature;

use App\Models\Coa;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WalletAndTransferTransactionTest extends TestCase
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

    public function test_transfer_with_null_source_wallet_increases_destination_wallet_balance(): void
    {
        $destinationWallet = Wallet::create(['name' => 'Test Dest Wallet', 'balance' => 0]);
        $transferCoa = Coa::create([
            'code' => '1-2000',
            'name' => 'Transfer Antar Dompet',
            'type' => 'asset',
            'category' => 'transfer',
            'is_active' => true,
        ]);

        $tx = Transaction::create([
            'name' => 'Transfer Masuk Eksternal',
            'user_id' => $this->user->id,
            'wallet_id' => null,
            'to_wallet_id' => $destinationWallet->id,
            'coa_id' => $transferCoa->id,
            'amount' => 250000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $this->assertEquals(250000, (float) $destinationWallet->fresh()->balance);

        $tx->delete();
        $this->assertEquals(0, (float) $destinationWallet->fresh()->balance);
    }

    public function test_transfer_between_two_wallets_updates_both_balances(): void
    {
        $sourceWallet = Wallet::create(['name' => 'Source Wallet', 'balance' => 0]);
        $destWallet = Wallet::create(['name' => 'Dest Wallet', 'balance' => 0]);

        $incomeCoa = Coa::create([
            'code' => '4-1000',
            'name' => 'Pendapatan',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);

        $transferCoa = Coa::create([
            'code' => '1-2000',
            'name' => 'Transfer Antar Dompet',
            'type' => 'asset',
            'category' => 'transfer',
            'is_active' => true,
        ]);

        // Add 500,000 to source wallet
        Transaction::create([
            'name' => 'Top Up',
            'user_id' => $this->user->id,
            'wallet_id' => $sourceWallet->id,
            'coa_id' => $incomeCoa->id,
            'amount' => 500000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $this->assertEquals(500000, (float) $sourceWallet->fresh()->balance);
        $this->assertEquals(0, (float) $destWallet->fresh()->balance);

        // Transfer 200,000 from source to dest
        $transferTx = Transaction::create([
            'name' => 'Transfer Internal',
            'user_id' => $this->user->id,
            'wallet_id' => $sourceWallet->id,
            'to_wallet_id' => $destWallet->id,
            'coa_id' => $transferCoa->id,
            'amount' => 200000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $this->assertEquals(300000, (float) $sourceWallet->fresh()->balance);
        $this->assertEquals(200000, (float) $destWallet->fresh()->balance);
    }

    public function test_non_transfer_requires_wallet_id(): void
    {
        $this->expectException(ValidationException::class);

        $incomeCoa = Coa::create([
            'code' => '4-1000',
            'name' => 'Pendapatan Lain',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);

        Transaction::create([
            'name' => 'Pemasukan Tanpa Dompet',
            'user_id' => $this->user->id,
            'wallet_id' => null,
            'coa_id' => $incomeCoa->id,
            'amount' => 100000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);
    }

    public function test_balance_adjustment_creates_proper_transaction_and_updates_balance(): void
    {
        $wallet = Wallet::create(['name' => 'Test Adjust Wallet', 'balance' => 0]);
        $incomeCoa = Coa::create([
            'code' => '4-2000',
            'name' => 'Pendapatan Lain',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);
        $expenseCoa = Coa::create([
            'code' => '5-2000',
            'name' => 'Beban Operasional',
            'type' => 'expense',
            'category' => 'pengeluaran',
            'is_active' => true,
        ]);

        // Adjust balance UP from 0 to 500,000
        $txUp = Transaction::create([
            'name' => 'Penyesuaian Saldo',
            'user_id' => $this->user->id,
            'wallet_id' => $wallet->id,
            'coa_id' => $incomeCoa->id,
            'amount' => 500000,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Penyesuaian saldo naik',
        ]);

        $this->assertEquals(500000, (float) $wallet->fresh()->balance);

        // Adjust balance DOWN from 500,000 to 300,000 (diff = 200,000)
        $txDown = Transaction::create([
            'name' => 'Penyesuaian Saldo',
            'user_id' => $this->user->id,
            'wallet_id' => $wallet->id,
            'coa_id' => $expenseCoa->id,
            'amount' => 200000,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => 'Penyesuaian saldo turun',
        ]);

        $this->assertEquals(300000, (float) $wallet->fresh()->balance);
    }
}
