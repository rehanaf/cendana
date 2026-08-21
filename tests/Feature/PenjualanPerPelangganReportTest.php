<?php

namespace Tests\Feature;

use App\Livewire\PenjualanPerPelangganTable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PenjualanPerPelangganReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', database_path('database.sqlite'));
        DB::purge('sqlite');
    }

    public function test_report_page_renders_with_data(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Test ini untuk SQLite.');
        }

        $user = User::query()->first();
        $this->assertNotNull($user, 'No user in DB');
        $this->actingAs($user);

        $response = $this->get('admin/laporan-penjualan-per-pelanggan');
        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringContainsString('Corporate', $html);
        $this->assertStringContainsString('Retail', $html);
        $this->assertStringContainsString('Total Penjualan', $html);
        $this->assertStringContainsString('Total Langganan', $html);
        $this->assertStringContainsString('Jumlah Pelanggan Corporate', $html);
        $this->assertStringContainsString('Jumlah Pelanggan Retail', $html);
        $this->assertStringContainsString('PT Cendana Teknologi', $html);
    }

    public function test_corporate_table_has_rows_and_view_action(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Test ini untuk SQLite.');
        }

        $user = User::query()->first();
        $this->actingAs($user);

        Livewire::test(PenjualanPerPelangganTable::class, ['source' => 'corporate'])
            ->assertSee('PT Cendana Teknologi')
            ->assertSee('CV Karya Abadi')
            ->assertSee('Lihat Invoice');

        Livewire::test(PenjualanPerPelangganTable::class, ['source' => 'retail'])
            ->assertSee('Budi Santoso')
            ->assertSee('Siti Rahayu')
            ->assertSee('Lihat Invoice');
    }

    public function test_view_action_lists_invoices(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Test ini untuk SQLite.');
        }

        $user = User::query()->first();
        $this->actingAs($user);

        Livewire::test(PenjualanPerPelangganTable::class, ['source' => 'corporate'])
            ->mountTableAction('view', 'PT Cendana Teknologi')
            ->assertActionMounted([
                [
                    'name' => 'view',
                    'context' => [
                        'table' => true,
                        'recordKey' => 'PT Cendana Teknologi',
                    ],
                ]
            ])
            ->assertMountedActionModalSee('SUB-')
            ->assertMountedActionModalSee('Penjualan')
            ->assertMountedActionModalSee('Langganan');
    }

    public function test_daftar_penjualan_page_renders_with_data(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Test ini untuk SQLite.');
        }

        $user = User::query()->first();
        $this->actingAs($user);

        $response = $this->get('admin/laporan-daftar-penjualan');
        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringContainsString('Daftar Penjualan', $html);
        $this->assertStringContainsString('Total Penjualan', $html);
        $this->assertStringContainsString('Tipe Pelanggan', $html);
    }
}
