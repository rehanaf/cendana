<?php

namespace App\Console\Commands;

use App\Services\RetailImportService;
use Illuminate\Console\Command;

class ImportRetailCustomers extends Command
{
    protected $signature = 'retail:import
        {--file= : Path file CSV (default: customer_retail.csv di root project)}';

    protected $description = 'Import pelanggan retail dari file CSV (customer_retail.csv) dan otomatis hubungkan paket internet';

    public function handle(RetailImportService $service): int
    {
        $path = $this->option('file') ?: base_path('customer_retail.csv');

        $this->info("Memproses file: {$path}");

        $stats = $service->import($path);

        $this->info('Selesai.');
        $this->newLine();
        $this->info("Pelanggan baru : {$stats['created']}");
        $this->info("Pelanggan diperbarui : {$stats['updated']}");
        $this->info("Total baris diproses : {$stats['rows']}");
        $this->info("Tanpa paket (NULL)  : {$stats['no_package']}");

        if ($stats['issues']) {
            $this->newLine();
            $this->warn('Catatan:');
            foreach ($stats['issues'] as $issue) {
                $this->line("  - {$issue}");
            }
        }

        return self::SUCCESS;
    }
}