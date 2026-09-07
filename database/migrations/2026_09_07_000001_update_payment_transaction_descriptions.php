<?php

use App\Models\Purchase;
use App\Models\RetailInvoice;
use App\Models\Sale;
use App\Models\SubscriptionInvoice;
use App\Models\Transaction;
use App\Support\PaymentDescription;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $links = [
            ['column' => 'sale_id', 'model' => Sale::class, 'relations' => ['customer'], 'prefix' => 'Pembayaran', 'type' => 'penjualan'],
            ['column' => 'purchase_id', 'model' => Purchase::class, 'relations' => ['vendor'], 'prefix' => 'Pembayaran', 'type' => 'pembelian'],
            ['column' => 'subscription_invoice_id', 'model' => SubscriptionInvoice::class, 'relations' => ['customer'], 'prefix' => 'Pembayaran Langganan', 'type' => 'langganan'],
            ['column' => 'retail_invoice_id', 'model' => RetailInvoice::class, 'relations' => ['customer'], 'prefix' => 'Pembayaran Retail', 'type' => 'langganan'],
        ];

        foreach ($links as $link) {
            Transaction::query()
                ->whereNotNull($link['column'])
                ->orderBy('id')
                ->chunkById(200, function ($transactions) use ($link) {
                    foreach ($transactions as $transaction) {
                        $linked = $link['model']::query()
                            ->with($link['relations'])
                            ->find($transaction->{$link['column']});

                        if ($linked === null || blank(trim((string) $transaction->description))) {
                            continue;
                        }

                        $old = trim($transaction->description);

                        if (! str_starts_with(strtolower($old), 'pembayaran')
                            || ! str_contains($old, $linked->invoice_no)) {
                            continue;
                        }

                        $personName = method_exists($linked, 'customer') && $linked->customer?->name
                            ? $linked->customer->name
                            : (method_exists($linked, 'vendor') ? $linked->vendor?->name : null);

                        $new = PaymentDescription::make($link['prefix'], $link['type'], $linked->notes, $personName);

                        if ($new !== '' && $new !== $old) {
                            $transaction->forceFill(['description' => $new])->save();
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Tidak ada pembalikan yang aman: deskripsi lama tidak disimpan terpisah.
    }
};
