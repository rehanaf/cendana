@php
    $hutangPenjualan = $sales->sum(fn ($sale) => $sale->sisa);
    $hutangLangganan = $subscriptions->sum(fn ($invoice) => $invoice->sisa);
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Hutang Penjualan</p>
            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">Rp {{ number_format($hutangPenjualan, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Hutang Langganan</p>
            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">Rp {{ number_format($hutangLangganan, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-950/40">
            <p class="text-xs font-medium uppercase tracking-wide text-red-600 dark:text-red-400">Total Hutang</p>
            <p class="mt-1 text-lg font-bold text-red-700 dark:text-red-300">Rp {{ number_format($hutangPenjualan + $hutangLangganan, 0, ',', '.') }}</p>
        </div>
    </div>

    <div>
        <h4 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Piutang Penjualan</h4>

        @if ($sales->isEmpty())
            <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada penjualan yang belum lunas.</p>
        @else
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">No. Nota</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Tanggal</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Jatuh Tempo</th>
                            <th class="px-4 py-2 text-right font-semibold text-slate-600 dark:text-slate-300">Total</th>
                            <th class="px-4 py-2 text-right font-semibold text-slate-600 dark:text-slate-300">Dibayar</th>
                            <th class="px-4 py-2 text-right font-semibold text-slate-600 dark:text-slate-300">Sisa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($sales as $sale)
                            <tr>
                                <td class="px-4 py-2 font-medium text-slate-800 dark:text-slate-100">{{ $sale->invoice_no }}</td>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $sale->date->translatedFormat('d F Y') }}</td>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $sale->due_date->translatedFormat('d F Y') }}</td>
                                <td class="px-4 py-2 text-right text-slate-800 dark:text-slate-100">Rp {{ number_format((float) $sale->total, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">Rp {{ number_format($sale->total_paid, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-red-600 dark:text-red-400">Rp {{ number_format($sale->sisa, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div>
        <h4 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Piutang Langganan</h4>

        @if ($subscriptions->isEmpty())
            <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada tagihan langganan yang belum lunas.</p>
        @else
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">No. Invoice</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Periode</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Jatuh Tempo</th>
                            <th class="px-4 py-2 text-right font-semibold text-slate-600 dark:text-slate-300">Total</th>
                            <th class="px-4 py-2 text-right font-semibold text-slate-600 dark:text-slate-300">Dibayar</th>
                            <th class="px-4 py-2 text-right font-semibold text-slate-600 dark:text-slate-300">Sisa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($subscriptions as $invoice)
                            <tr>
                                <td class="px-4 py-2 font-medium text-slate-800 dark:text-slate-100">{{ $invoice->invoice_no }}</td>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $invoice->period->translatedFormat('F Y') }}</td>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $invoice->due_date->translatedFormat('d F Y') }}</td>
                                <td class="px-4 py-2 text-right text-slate-800 dark:text-slate-100">Rp {{ number_format((float) $invoice->total, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">Rp {{ number_format($invoice->total_paid, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-red-600 dark:text-red-400">Rp {{ number_format($invoice->sisa, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>