<?php

namespace App\Services;

use App\Models\Coa;
use App\Models\PelangganRetail;
use App\Models\RetailInvoice;
use App\Models\Setting;
use Illuminate\Support\Carbon;

class RetailInvoiceService
{
    public function generateForPeriod(int $year, int $month, ?int $customerId = null): array
    {
        $period = Carbon::create($year, $month, 1);

        $query = PelangganRetail::query()
            ->where('is_active', true)
            ->whereNotNull('internet_package_id')
            ->with('paketInternet');

        if ($customerId) {
            $query->where('id', $customerId);
        }

        $customers = $query->get();

        $coaId = (int) Setting::get('coa_retail_id');
        $coa = $coaId
            ? Coa::find($coaId)
            : Coa::where('category', 'pemasukan')->where('type', 'income')->orderBy('code')->first();

        $dueDay = max(1, min(28, (int) Setting::get('retail_generate_day', 1)));

        $created = 0;
        $skipped = 0;

        foreach ($customers as $customer) {
            $package = $customer->paketInternet;

            if (! $package || (float) $package->price <= 0) {
                $skipped++;

                continue;
            }

            $exists = RetailInvoice::where('retail_customer_id', $customer->id)
                ->whereYear('period', $period->year)
                ->whereMonth('period', $period->month)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $dueDate = $period->copy()->day($dueDay);

            $invoiceNo = 'RTL-' . $period->format('Ym') . '-' . str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT);

            RetailInvoice::create([
                'invoice_no' => $invoiceNo,
                'retail_customer_id' => $customer->id,
                'internet_package_id' => $package->id,
                'period' => $period,
                'date' => now(),
                'due_date' => $dueDate,
                'coa_id' => $coa?->id,
                'wallet_id' => Setting::getWalletId('wallet_retail_id'),
                'total' => (float) $package->price,
                'status' => 'berjalan',
                'created_by' => auth()->id(),
            ]);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}