<?php

namespace Tests\Feature;

use App\Filament\Pages\Laporan\LaporanDaftarPembelian;
use App\Models\Coa;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExposedLaporanDaftarPembelian extends LaporanDaftarPembelian
{
    public function exposeQuery(bool $applyWallet = true)
    {
        return $this->getQuery($applyWallet);
    }
}

class LaporanDaftarPembelianTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Wallet $wallet;

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
    }

    public function test_daftar_pembelian_menampilkan_nota_dan_transaksi_hpp_manual(): void
    {
        $hppCoa = Coa::create([
            'code' => '5-1000',
            'name' => 'Pembelian Barang',
            'type' => 'cogs',
            'category' => 'pengeluaran',
            'is_active' => true,
        ]);

        $vendor = Vendor::create(['name' => 'PT Supplier']);
        $purchase = Purchase::create([
            'invoice_no' => 'PO-001',
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'vendor_id' => $vendor->id,
            'coa_id' => $hppCoa->id,
            'wallet_id' => $this->wallet->id,
            'total' => 500000,
            'status' => 'berjalan',
            'created_by' => $this->user->id,
        ]);

        Transaction::create([
            'name' => 'HPP Manual',
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'coa_id' => $hppCoa->id,
            'amount' => 250000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $page = new ExposedLaporanDaftarPembelian();
        $page->mode = 'semua';

        $rows = $page->exposeQuery()->get();
        $this->assertCount(2, $rows);

        $sumbers = $rows->pluck('sumber_label')->sort()->values();
        $this->assertContains('Nota Pembelian', $sumbers);
        $this->assertContains('Pengeluaran HPP', $sumbers);

        $manual = $rows->first(fn ($r) => $r->sumber_label === 'Pengeluaran HPP');
        $this->assertEquals('Pembelian Barang', $manual->coa_name);
        $this->assertEquals(250000, (float) $manual->total);
        $this->assertEquals(250000, (float) $manual->paid);
        $this->assertEquals('lunas', $manual->status);

        $this->assertSame($hppCoa->id, (int) $purchase->fresh()->coa_id);
        $page->coaId = (string) $hppCoa->id;
        $this->assertCount(2, $page->exposeQuery()->get());
    }

    public function test_daftar_pembelian_hanya_ambil_coa_hpp_aktif(): void
    {
        $hppCoa = Coa::create([
            'code' => '5-1000',
            'name' => 'Pembelian Barang',
            'type' => 'cogs',
            'category' => 'pengeluaran',
            'is_active' => true,
        ]);
        $inactiveCoa = Coa::create([
            'code' => '5-2000',
            'name' => 'Beban Nonaktif',
            'type' => 'cogs',
            'category' => 'pengeluaran',
            'is_active' => false,
        ]);

        Transaction::create([
            'name' => 'HPP Aktif',
            'description' => 'HPP Aktif',
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'coa_id' => $hppCoa->id,
            'amount' => 100000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);
        Transaction::create([
            'name' => 'HPP Nonaktif',
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'coa_id' => $inactiveCoa->id,
            'amount' => 50000,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $page = new ExposedLaporanDaftarPembelian();
        $page->mode = 'semua';

        $rows = $page->exposeQuery()->get();

        $this->assertCount(1, $rows);
        $this->assertEquals('Pembelian Barang', $rows->first()->coa_name);
        $this->assertEquals('Pengeluaran HPP', $rows->first()->sumber_label);
        $this->assertEquals('HPP Aktif', $rows->first()->invoice_no);
    }
}
