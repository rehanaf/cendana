<div
    wire:loading
    wire:target="{{ $targets ?? 'mode, date, reportMonth, reportYear, periodStart, periodEnd' }}"
    class="mb-3 flex items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-sm text-primary-700 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-300"
>
    <x-filament::loading-indicator class="h-4 w-4" />
    <span>Memuat data...</span>
</div>