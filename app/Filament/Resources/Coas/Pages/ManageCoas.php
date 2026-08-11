<?php

namespace App\Filament\Resources\Coas\Pages;

use App\Filament\Resources\Coas\CoaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCoas extends ManageRecords
{
    protected static string $resource = CoaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Akun')
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
        ];
    }
}
