<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="rounded-xl border border-gray-200 p-5 dark:border-white/10">
        <div class="mb-4 flex items-center gap-2">
            <x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5 text-success-500" />
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Aset</h3>
        </div>
        <dl class="space-y-3">
            <div class="flex items-center justify-between text-sm">
                <dt class="text-gray-500 dark:text-gray-400">Kas &amp; Bank</dt>
                <dd class="font-medium text-gray-900 dark:text-white">Rp {{ number_format($kas, 0, ',', '.') }}</dd>
            </div>
            <div class="flex items-center justify-between text-sm">
                <dt class="text-gray-500 dark:text-gray-400">Piutang Usaha</dt>
                <dd class="font-medium text-gray-900 dark:text-white">Rp {{ number_format($piutang, 0, ',', '.') }}</dd>
            </div>
            <div class="border-t border-gray-200 pt-2 dark:border-white/10">
                <div class="flex items-center justify-between text-sm font-bold">
                    <dt class="text-gray-900 dark:text-white">Total Aset</dt>
                    <dd class="text-success-600 dark:text-success-400">Rp {{ number_format($total_aset, 0, ',', '.') }}</dd>
                </div>
            </div>
        </dl>
    </div>

    <div class="rounded-xl border border-gray-200 p-5 dark:border-white/10">
        <div class="mb-4 flex items-center gap-2">
            <x-filament::icon icon="heroicon-o-arrow-trending-down" class="h-5 w-5 text-danger-500" />
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Kewajiban</h3>
        </div>
        <dl class="space-y-3">
            <div class="flex items-center justify-between text-sm">
                <dt class="text-gray-500 dark:text-gray-400">Utang Usaha</dt>
                <dd class="font-medium text-gray-900 dark:text-white">Rp {{ number_format($utang, 0, ',', '.') }}</dd>
            </div>
            <div class="border-t border-gray-200 pt-2 dark:border-white/10">
                <div class="flex items-center justify-between text-sm font-bold">
                    <dt class="text-gray-900 dark:text-white">Total Kewajiban</dt>
                    <dd class="text-danger-600 dark:text-danger-400">Rp {{ number_format($total_kewajiban, 0, ',', '.') }}</dd>
                </div>
            </div>
        </dl>
    </div>

    <div class="rounded-xl border border-gray-200 p-5 dark:border-white/10">
        <div class="mb-4 flex items-center gap-2">
            <x-filament::icon icon="heroicon-o-chart-bar" class="h-5 w-5 text-primary-500" />
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Ekuitas</h3>
        </div>
        <dl class="space-y-3">
            <div class="flex items-center justify-between text-sm">
                <dt class="text-gray-500 dark:text-gray-400">Saldo Laba</dt>
                <dd class="font-medium text-gray-900 dark:text-white">Rp {{ number_format($laba, 0, ',', '.') }}</dd>
            </div>
            <div class="border-t border-gray-200 pt-2 dark:border-white/10">
                <div class="flex items-center justify-between text-sm font-bold">
                    <dt class="text-gray-900 dark:text-white">Total Ekuitas</dt>
                    <dd class="text-primary-600 dark:text-primary-400">Rp {{ number_format($total_ekuitas, 0, ',', '.') }}</dd>
                </div>
            </div>
        </dl>
    </div>
</div>
