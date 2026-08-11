<?php

namespace App\Filament\Resources\PaketInternets\Pages;

use App\Filament\Resources\PaketInternets\PaketInternetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePaketInternets extends ManageRecords
{
    protected static string $resource = PaketInternetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Paket'),
        ];
    }
}
