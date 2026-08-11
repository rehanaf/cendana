<?php

namespace App\Filament\Resources\PelangganRetails\Pages;

use App\Filament\Resources\PelangganRetails\PelangganRetailResource;
use App\Services\RetailInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManagePelangganRetails extends ManageRecords
{
    protected static string $resource = PelangganRetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateRetailInvoice')
                ->label('Generate Invoice')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generate Invoice Bulan Ini')
                ->modalDescription('Buat tagihan retail untuk periode bulan berjalan sesuai paket internet pelanggan.')
                ->modalSubmitActionLabel('Generate')
                ->action(function (RetailInvoiceService $service): void {
                    $result = $service->generateForPeriod(now()->year, now()->month);

                    Notification::make()
                        ->success()
                        ->title('Selesai')
                        ->body("Dibuat: {$result['created']}, dilewati: {$result['skipped']}.")
                        ->send();
                }),
            CreateAction::make()
                ->label('Tambah Pelanggan'),
        ];
    }
}
