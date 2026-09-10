<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Resources\Transactions\TransactionsColumnDefaults;
use App\Models\Wallet;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ManageRecords
{
    protected static string $resource = TransactionResource::class;

    public bool $hasAppliedColumnDefaultsOnLoad = false;

    public function rendering(): void
    {
        if ($this->hasAppliedColumnDefaultsOnLoad) {
            return;
        }

        $this->hasAppliedColumnDefaultsOnLoad = true;

        $this->applyColumnDefaultsForActiveTab();
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();

        $this->cachedDefaultTableColumnState = null;

        $this->applyColumnDefaultsForActiveTab();

        $this->applyTableColumnManager();
    }

    /**
     * Terapkan kolom default (prioritas admin) untuk tab dompet yang aktif.
     */
    protected function applyColumnDefaultsForActiveTab(): void
    {
        $visible = TransactionsColumnDefaults::visibleForTab($this->activeTab);

        if ($visible === null) {
            return;
        }

        $state = $this->getDefaultTableColumnState();

        foreach ($state as &$item) {
            if (($item['type'] ?? null) !== 'column') {
                continue;
            }

            if (! ($item['isToggleable'] ?? false)) {
                $item['isToggled'] = true;

                continue;
            }

            $item['isToggled'] = in_array($item['name'], $visible, true);
        }

        $this->setTableColumns($state);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Transaksi')
                ->mountUsing(function (CreateAction $action, ?Schema $schema): void {
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
                'wallet_'.$wallet->id => Tab::make($wallet->name)
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('wallet_id', $wallet->id)),
            ]),
        ];
    }
}
