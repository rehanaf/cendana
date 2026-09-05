<?php

namespace Tests\Feature;

use App\Filament\Resources\Coas\CoaResource;
use App\Models\Coa;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoaReassignDeleteTest extends TestCase
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

    public function test_reassign_coa_moves_transactions_and_preserves_wallet_balance(): void
    {
        $wallet = Wallet::create(['name' => 'Tunai', 'balance' => 0]);

        $source = Coa::create([
            'code' => '90001',
            'name' => 'COA Sumber',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);

        $target = Coa::create([
            'code' => '90002',
            'name' => 'COA Tujuan',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);

        Transaction::create([
            'name' => 'Test',
            'user_id' => $this->user->id,
            'wallet_id' => $wallet->id,
            'coa_id' => $source->id,
            'amount' => 500000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $this->assertEquals(500000, (float) $wallet->fresh()->balance);
        $this->assertNotEmpty($source->usageLabels());

        CoaResource::reassignCoaData($source, $target);

        $moved = Transaction::first();
        $this->assertSame($target->id, $moved->coa_id);
        $this->assertSame(0, $source->transactions()->count());
        $this->assertDatabaseMissing('transactions', ['coa_id' => $source->id, 'id' => $moved->id]);

        $source->delete();
        $this->assertDatabaseMissing('coas', ['id' => $source->id]);

        $this->assertEquals(500000, (float) $wallet->fresh()->balance);
        $this->assertEquals(500000, (float) Transaction::where('coa_id', $target->id)->first()->amount);
    }

    public function test_reassign_coa_clears_to_wallet_when_target_is_not_transfer(): void
    {
        $sourceWallet = Wallet::create(['name' => 'Kas', 'balance' => 0]);
        $destWallet = Wallet::create(['name' => 'Bank', 'balance' => 0]);

        $transferSource = Coa::create([
            'code' => '90003',
            'name' => 'Transfer Sumber',
            'type' => 'asset',
            'category' => 'transfer',
            'is_active' => true,
        ]);

        $expenseTarget = Coa::create([
            'code' => '90004',
            'name' => 'Beban Pengganti',
            'type' => 'expense',
            'category' => 'pengeluaran',
            'is_active' => true,
        ]);

        $tx = Transaction::create([
            'name' => 'Transfer Antar Dompet',
            'user_id' => $this->user->id,
            'wallet_id' => $sourceWallet->id,
            'to_wallet_id' => $destWallet->id,
            'coa_id' => $transferSource->id,
            'amount' => 100000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $this->assertEquals(-100000, (float) $sourceWallet->fresh()->balance);
        $this->assertEquals(100000, (float) $destWallet->fresh()->balance);

        CoaResource::reassignCoaData($transferSource, $expenseTarget);

        $this->assertSame($expenseTarget->id, $tx->fresh()->coa_id);
        $this->assertNull($tx->fresh()->to_wallet_id);

        $transferSource->delete();
        $this->assertDatabaseMissing('coas', ['id' => $transferSource->id]);
    }
}