<?php

namespace App\Console\Commands;

use App\Services\SubscriptionInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BillSubscriptionCommand extends Command
{
    protected $signature = 'subscription:bill {--date= : Tanggal untuk billing (Y-m-d), default hari ini}';

    protected $description = 'Buat tagihan langganan bulanan untuk semua pelanggan corporate aktif';

    public function handle(SubscriptionInvoiceService $service): int
    {
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today();

        $result = $service->generateForPeriod($today->year, $today->month);

        $this->info("Selesai. Dibuat: {$result['created']}, dilewati: {$result['skipped']}.");

        return self::SUCCESS;
    }
}
