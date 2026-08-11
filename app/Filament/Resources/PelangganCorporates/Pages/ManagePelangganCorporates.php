<?php

namespace App\Filament\Resources\PelangganCorporates\Pages;

use App\Filament\Resources\PelangganCorporates\PelangganCorporateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePelangganCorporates extends ManageRecords
{
    protected static string $resource = PelangganCorporateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pelanggan'),
        ];
    }
}
