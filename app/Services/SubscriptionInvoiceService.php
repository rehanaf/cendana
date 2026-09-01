<?php

namespace App\Services;

use App\Models\Coa;
use App\Models\PelangganCorporate;
use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use Illuminate\Support\Carbon;

class SubscriptionInvoiceService
{
    public function generateForPeriod(int $year, int $month, ?int $customerId = null): array
    {
        $period = Carbon::create($year, $month, 1);

        $query = PelangganCorporate::query()
            ->where('is_subscription', true)
            ->where('is_active', true);

        if ($customerId) {
            $query->where('id', $customerId);
        }

        $customers = $query->get();

        $coaId = (int) Setting::get('coa_langganan_id');
        $coa = $coaId
            ? Coa::find($coaId)
            : Coa::where('category', 'pemasukan')->where('type', 'income')->orderBy('code')->first();

        $created = 0;
        $skipped = 0;

        foreach ($customers as $customer) {
            $exists = SubscriptionInvoice::where('customer_id', $customer->id)
                ->whereYear('period', $period->year)
                ->whereMonth('period', $period->month)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $total = (float) $customer->monthly_fee;

            if ($total <= 0) {
                $skipped++;

                continue;
            }

            $dueDay = max(1, min(28, (int) $customer->due_day));
            $dueDate = $period->copy()->day($dueDay);

            $invoiceNo = 'SUB-' . $period->format('Ym') . '-' . str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT);

            SubscriptionInvoice::create([
                'invoice_no' => $invoiceNo,
                'customer_id' => $customer->id,
                'period' => $period,
                'date' => now(),
                'due_date' => $dueDate,
                'coa_id' => $coa?->id,
                'wallet_id' => Setting::getWalletId('wallet_langganan_id'),
                'total' => $total,
                'notes' => $customer->notes,
                'status' => 'berjalan',
                'created_by' => auth()->id(),
            ]);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}