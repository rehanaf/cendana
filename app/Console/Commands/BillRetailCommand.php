<?php

namespace App\Console\Commands;

use App\Services\RetailInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BillRetailCommand extends Command
{
    protected $signature = 'retail:bill
        {--date= : Tanggal untuk billing (Y-m-d), default hari ini}';

    protected $description = 'Buat tagihan retail bulanan untuk semua pelanggan retail aktif berdasarkan paket internet';

    public function handle(RetailInvoiceService $service): int
    {
        $today = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        $result = $service->generateForPeriod($today->year, $today->month);

        $this->info("Selesai. Dibuat: {$result['created']}, dilewati: {$result['skipped']}.");

        return self::SUCCESS;
    }
}