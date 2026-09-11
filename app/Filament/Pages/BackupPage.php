<?php

namespace App\Filament\Pages;

use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupPage extends Page
{
    protected string $view = 'filament.pages.backup';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 5;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedArchiveBox;
    }

    public static function getNavigationLabel(): string
    {
        return 'Backup Database';
    }

    public function getTitle(): string
    {
        return 'Backup Database';
    }

    public static function getDefaultSlug(): string
    {
        return 'backup-database';
    }

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->isAdmin());
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function backupDirectory(): string
    {
        return (string) config('backup.backup.name');
    }

    /** @return array<int, array{path: string, name: string, size: int, human_size: string, created_at: string}> */
    public function getBackups(): array
    {
        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'backups');

        return collect($disk->allFiles($this->backupDirectory()))
            ->sortByDesc(fn (string $file): string => $file)
            ->values()
            ->map(fn (string $file): array => [
                'path' => $file,
                'name' => basename($file),
                'size' => (int) $disk->size($file),
                'human_size' => $this->humanSize($disk->size($file)),
                'created_at' => $this->createdAtLabel($file),
            ])
            ->all();
    }

    public function getBackupStats(): array
    {
        $backups = $this->getBackups();
        $totalSize = array_sum(array_column($backups, 'size'));
        $last = $backups[0] ?? null;

        return [
            'count' => count($backups),
            'total_size' => $this->humanSize($totalSize),
            'last' => $last['name'] ?? null,
            'last_created_at' => $last['created_at'] ?? null,
            'disk' => storage_path('app/backups'),
        ];
    }

    public function runBackup(): void
    {
        try {
            $code = Artisan::call('backup:run', [
                '--no-interaction' => true,
                '--disable-notifications' => true,
            ]);

            if ($code !== 0) {
                Notification::make()
                    ->danger()
                    ->title('Backup gagal')
                    ->body(trim(Artisan::output()))
                    ->send();

                return;
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Backup gagal')
                ->body($e->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Backup berhasil dibuat')
            ->body('Cadangan database & konfigurasi telah disimpan.')
            ->send();
    }

    public function runCleanup(): void
    {
        try {
            $code = Artisan::call('backup:clean', [
                '--no-interaction' => true,
                '--disable-notifications' => true,
            ]);

            if ($code !== 0) {
                Notification::make()
                    ->danger()
                    ->title('Pembersihan backup gagal')
                    ->body(trim(Artisan::output()))
                    ->send();

                return;
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Pembersihan backup gagal')
                ->body($e->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Pembersihan backup selesai')
            ->body('Backup lama yang melewati periode retensi telah dihapus.')
            ->send();
    }

    public function downloadBackup(string $path): StreamedResponse
    {
        abort_if(! str_contains($path, '/'), 404);

        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'backups');

        abort_unless($disk->exists($path), 404);

        return $disk->download($path);
    }

    public function deleteBackup(string $path): void
    {
        abort_if(! str_contains($path, '/'), 404);

        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'backups');

        if (! $disk->exists($path)) {
            Notification::make()
                ->danger()
                ->title('File tidak ditemukan')
                ->send();

            return;
        }

        $disk->delete($path);

        Notification::make()
            ->success()
            ->title('Backup dihapus')
            ->body(basename($path).' telah dihapus.')
            ->send();
    }

    protected function createdAtLabel(string $file): string
    {
        preg_match('/-(\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2})\.zip$/i', $file, $m);

        if (isset($m[1])) {
            try {
                return Carbon::createFromFormat('Y-m-d-H-i-s', $m[1])
                    ->setTimezone(config('app.timezone'))
                    ->translatedFormat('d M Y H:i');
            } catch (\Throwable) {
                // fallthrough
            }
        }

        return '-';
    }

    protected function humanSize(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i > 1 ? 2 : 0, ',', '.').' '.$units[$i];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buat_backup')
                ->label('Buat Backup Sekarang')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->action(fn (): mixed => $this->runBackup()),
            Action::make('bersihkan_backup')
                ->label('Bersihkan Backup Lama')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Bersihkan Backup Lama')
                ->modalDescription('Backup yang sudah melewati periode penyimpanan akan dihapus. Backup paling baru tidak akan pernah dihapus. Lanjutkan?')
                ->modalSubmitActionLabel('Ya, Bersihkan')
                ->action(fn (): mixed => $this->runCleanup()),
        ];
    }
}
