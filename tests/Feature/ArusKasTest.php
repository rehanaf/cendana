<?php

namespace Tests\Feature;

use App\Filament\Pages\ArusKas;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ArusKasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', database_path('database.sqlite'));
        DB::purge('sqlite');
    }

    public function test_arus_kas_page_renders(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Test ini untuk SQLite.');
        }

        $user = User::query()->first();
        $this->assertNotNull($user, 'No user in DB');
        $this->actingAs($user);

        $response = $this->get('admin/arus-kas');
        $response->assertStatus(200);

        $this->assertStringContainsString('Arus Kas', $response->getContent());
        $this->assertStringContainsString('Tambah Transaksi', $response->getContent());
    }

    public function test_arus_kas_table_only_shows_manual_transactions(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Test ini untuk SQLite.');
        }

        $user = User::query()->first();
        $this->assertNotNull($user, 'No user in DB');
        $this->actingAs($user);

        $wallet = \App\Models\Wallet::query()->first();
        $coa = \App\Models\Coa::query()->where('category', 'pemasukan')->first() ?? \App\Models\Coa::query()->first();
        $this->assertNotNull($wallet, 'No wallet in DB');
        $this->assertNotNull($coa, 'No COA in DB');

        $manual = Transaction::query()->create([
            'name' => 'Manual Test Arus Kas',
            'description' => 'Pembayaran manual test',
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'coa_id' => $coa->id,
            'amount' => 10000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $sale = \App\Models\Sale::query()->create([
            'invoice_no' => 'SJ-TEST-'.uniqid(),
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'customer_id' => \App\Models\PelangganCorporate::query()->value('id'),
            'coa_id' => $coa->id,
            'wallet_id' => $wallet->id,
            'total' => 20000,
            'marketing_cost' => 0,
            'status' => 'berjalan',
            'created_by' => $user->id,
        ]);

        $linked = Transaction::query()->create([
            'name' => 'Otomatis Test Arus Kas',
            'description' => 'Pembayaran otomatis test',
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'coa_id' => $coa->id,
            'amount' => 20000,
            'transaction_date' => now()->format('Y-m-d'),
            'sale_id' => $sale->id,
        ]);

        try {
            $manualCount = Transaction::query()
                ->whereNull('sale_id')
                ->whereNull('purchase_id')
                ->whereNull('subscription_invoice_id')
                ->whereNull('retail_invoice_id')
                ->whereNull('transaction_reference_id')
                ->count();

            $this->assertGreaterThan(0, $manualCount, 'Harus ada transaksi manual.');
            $this->assertLessThan(Transaction::count(), $manualCount, 'Ada transaksi otomatis (ter-link) yang tidak boleh masuk Arus Kas.');

            Livewire::test(ArusKas::class)
                ->assertOk()
                ->assertDontSee('Pembayaran otomatis test');
        } finally {
            $linked->delete();
            $sale->delete();
            $manual->delete();
        }
    }
}
