<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Wallet;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ManageRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Transaksi')
                ->mountUsing(function (\Filament\Actions\CreateAction $action, ?\Filament\Schemas\Schema $schema): void {
                    $walletId = str_starts_with((string) $this->activeTab, 'wallet_')
                        ? (int) str_replace('wallet_', '', (string) $this->activeTab)
                        : null;

                    $schema?->fill($walletId ? ['wallet_id' => $walletId] : []);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = auth()->id();

                    return $data;
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            ...Wallet::orderBy('name')->get()->mapWithKeys(fn ($wallet) => [
                'wallet_' . $wallet->id => Tab::make($wallet->name)
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('wallet_id', $wallet->id)),
            ]),
        ];
    }
}
