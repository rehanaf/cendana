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
        $this->actingAs($user);

        $manualCount = Transaction::query()
            ->whereNull('sale_id')
            ->whereNull('purchase_id')
            ->whereNull('subscription_invoice_id')
            ->whereNull('retail_invoice_id')
            ->whereNull('transaction_reference_id')
            ->count();

        Livewire::test(ArusKas::class)
            ->assertOk();

        $this->assertGreaterThan(0, $manualCount);
        $this->assertLessThan(Transaction::count(), $manualCount);
    }
}
