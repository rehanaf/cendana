<x-filament-panels::page>
    @php
        $backups = $this->getBackups();
        $stats = $this->getBackupStats();
    @endphp

    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Backup</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $stats['count'] }}
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Ukuran</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $stats['total_size'] }}
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Backup Terakhir</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $stats['last_created_at'] ?? 'Belum ada' }}
                </div>
            </x-filament::section>
        </div>

        <x-filament::section
            icon="heroicon-o-archive-box"
            heading="Daftar Backup"
            description="Backup dibuat otomatis dari database (MySQL di hosting / SQLite di lokal) beserta file konfigurasi .env. Unduh lalu simpan di tempat aman."
        >
            @if (empty($backups))
                <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    Belum ada backup. Klik
                    <span class="font-medium text-gray-950 dark:text-white">Buat Backup Sekarang</span>
                    untuk membuat cadangan pertama.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="py-3 pr-4 text-left font-semibold text-gray-950 dark:text-white">
                                    Nama File
                                </th>
                                <th class="py-3 px-4 text-right font-semibold text-gray-950 dark:text-white">
                                    Ukuran
                                </th>
                                <th class="py-3 px-4 text-left font-semibold text-gray-950 dark:text-white">
                                    Dibuat Pada
                                </th>
                                <th class="py-3 pl-4 text-right font-semibold text-gray-950 dark:text-white">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($backups as $backup)
                                <tr class="border-b border-gray-100 dark:border-white/5 last:border-0">
                                    <td class="py-2.5 pr-4 font-medium text-gray-950 dark:text-white">
                                        {{ $backup['name'] }}
                                    </td>
                                    <td class="py-2.5 px-4 text-right text-gray-500 dark:text-gray-400">
                                        {{ $backup['human_size'] }}
                                    </td>
                                    <td class="py-2.5 px-4 text-gray-500 dark:text-gray-400">
                                        {{ $backup['created_at'] }}
                                    </td>
                                    <td class="py-2.5 pl-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <x-filament::button
                                                size="sm"
                                                color="gray"
                                                icon="heroicon-m-arrow-down-tray"
                                                wire:click="downloadBackup('{{ $backup['path'] }}')"
                                            >
                                                Unduh
                                            </x-filament::button>
                                            <x-filament::button
                                                size="sm"
                                                color="danger"
                                                icon="heroicon-m-trash"
                                                wire:confirm="Yakin ingin menghapus backup {{ $backup['name'] }}? Tindakan ini tidak dapat dibatalkan."
                                                wire:click="deleteBackup('{{ $backup['path'] }}')"
                                            >
                                                Hapus
                                            </x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section icon="heroicon-o-information-circle" class="dark:border-white/10">
            <ul class="space-y-1 text-sm text-gray-500 dark:text-gray-400 list-disc pl-4">
                <li>Backup disimpan di direktori: <code class="font-mono text-xs">{{ $stats['disk'] }}</code></li>
                <li>Backup harian dijadwalkan otomatis via cron (lihat <code class="font-mono text-xs">routes/console.php</code>).</li>
                <li>File backup berisi dum database + <code class="font-mono text-xs">.env</code>. JANGAN pernah menaruh file ini di folder publik.</li>
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>