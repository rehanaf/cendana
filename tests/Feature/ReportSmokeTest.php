<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ReportSmokeTest extends TestCase
{
    public function test_report_pages_render(): void
    {
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Membutuhkan koneksi MySQL.');
        }

        $user = User::query()->first();
        $this->assertNotNull($user, 'No user in DB');
        $this->actingAs($user);

        $urls = [
            'admin/laporan-keuangan',
            'admin/laporan-penjualan',
            'admin/laporan-pembelian',
            'admin/laporan-langganan',
            'admin/laporan-piutang',
            'admin/laporan-hutang',
            'admin/hutang-pelanggan-corporate',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);
            if ($response->getStatusCode() !== 200) {
                $ex = $response->exception;
                fwrite(STDERR, PHP_EOL . 'URL: ' . $url . PHP_EOL);
                fwrite(STDERR, 'EXCEPTION: ' . ($ex ? $ex->getMessage() : 'none') . PHP_EOL);
                fwrite(STDERR, 'TRACE: ' . ($ex ? $ex->getTraceAsString() : '') . PHP_EOL);
            }
            $this->assertTrue(
                $response->getStatusCode() === 200,
                "{$url} returned HTTP {$response->getStatusCode()}"
            );
        }
    }
}
