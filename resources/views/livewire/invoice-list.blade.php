<div>
    @if (count($invoices) === 0)
        <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada invoice pada periode ini.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-2 py-2">No. Invoice</th>
                        <th class="px-2 py-2">Sumber</th>
                        <th class="px-2 py-2">Tanggal</th>
                        <th class="px-2 py-2 text-right">Total</th>
                        <th class="px-2 py-2 text-right">Dibayar</th>
                        <th class="px-2 py-2 text-right">Sisa</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="px-2 py-2">{{ $invoice['no'] }}</td>
                            <td class="px-2 py-2">{{ $invoice['sumber'] }}</td>
                            <td class="px-2 py-2">{{ $invoice['date'] }}</td>
                            <td class="px-2 py-2 text-right">{{ $money((float) $invoice['total']) }}</td>
                            <td class="px-2 py-2 text-right text-emerald-600 dark:text-emerald-400">{{ $money((float) $invoice['dibayar']) }}</td>
                            <td class="px-2 py-2 text-right {{ (float) $invoice['total'] - (float) $invoice['dibayar'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $money((float) $invoice['total'] - (float) $invoice['dibayar']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>