<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use App\Models\Wallet;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArusKas extends Page implements HasTable
{
    use HasTabs;
    use InteractsWithTable;

    protected static ?int $navigationSort = 3;

    protected static string|\UnitEnum|null $navigationGroup = 'Kas';

    public static function getNavigationLabel(): string
    {
        return 'Arus Kas';
    }

    public function getTitle(): string
    {
        return 'Arus Kas';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedArrowsRightLeft;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->hasPermission('view_transactions');
    }

    public function table(Table $table): Table
    {
        return TransactionsTable::configure($table, manualOnly: true)
            ->query(Transaction::query())
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->reorderable(false);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            ...Wallet::orderBy('name')->get()->mapWithKeys(fn (Wallet $wallet) => [
                'wallet_' . $wallet->id => Tab::make($wallet->name)
                    ->modifyQueryUsing(fn (Builder $query) => TransactionsTable::applyWalletTabQuery($query, $wallet)),
            ]),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                $this->getTable(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Transaksi')
                ->mountUsing(function (CreateAction $action, ?Schema $form): void {
                    $walletId = str_starts_with((string) $this->activeTab, 'wallet_')
                        ? (int) str_replace('wallet_', '', (string) $this->activeTab)
                        : null;

                    $form?->fill($walletId ? ['wallet_id' => $walletId] : []);
                })
                ->form(TransactionForm::configure(...))
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = auth()->id();

                    return $data;
                })
                ->visible(fn (): bool => auth()->user()?->isAdmin() || auth()->user()?->hasPermission('create_transactions')),
        ];
    }
}
