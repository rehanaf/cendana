<?php

namespace App\Filament\Resources\TroubleTickets\Pages;

use App\Filament\Resources\TroubleTickets\TroubleTicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTroubleTickets extends ManageRecords
{
    protected static string $resource = TroubleTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Tiket'),
        ];
    }
}
