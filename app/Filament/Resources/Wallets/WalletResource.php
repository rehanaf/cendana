<?php

namespace App\Filament\Resources\Wallets;

use App\Filament\Resources\Wallets\Pages\ManageWallets;
use App\Models\Wallet;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class WalletResource extends Resource
{
    protected static ?string $model = Wallet::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Kas';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return Heroicon::OutlinedWallet;
    }

    public static function getNavigationLabel(): string
    {
        return 'Dompet';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Dompet';
    }

    public static function getModelLabel(): string
    {
        return 'Dompet';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->hasPermission('view_wallets'));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->hasPermission('manage_wallets');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->hasPermission('manage_wallets');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->hasPermission('manage_wallets');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Dompet')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Dompet')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('balance')
                    ->label('Saldo')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable()
                    ->color(fn (float $state): string => $state < 0 ? 'danger' : 'success'),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(50),
                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() || auth()->user()?->hasPermission('manage_wallets')),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() || auth()->user()?->hasPermission('manage_wallets')),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() || auth()->user()?->hasPermission('manage_wallets'))
                    ->action(function (DeleteAction $action, Wallet $record): void {
                        $usage = $record->usageLabels();

                        if ($usage->isNotEmpty()) {
                            Notification::make()
                                ->danger()
                                ->title('Dompet tidak dapat dihapus')
                                ->body('Masih dipakai oleh: ' . $usage->implode(', ') . '. Nonaktifkan dompet ini bila tidak digunakan lagi.')
                                ->persistent()
                                ->send();

                            $action->halt();

                            return;
                        }

                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Dompet berhasil dihapus')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus yang Dipilih')
                        ->action(function (DeleteBulkAction $action, $records): void {
                            $blocked = collect();

                            $records->each(function (Wallet $wallet) use ($blocked): void {
                                if ($wallet->usageLabels()->isNotEmpty()) {
                                    $blocked->push($wallet->name);
                                }
                            });

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->danger()
                                    ->title('Sebagian dompet tidak dapat dihapus')
                                    ->body('Masih dipakai oleh: ' . $blocked->implode(', ') . '. Nonaktifkan dompet tersebut bila tidak digunakan lagi.')
                                    ->persistent()
                                    ->send();

                                $action->halt();

                                return;
                            }

                            $records->each->delete();

                            Notification::make()
                                ->success()
                                ->title('Dompet berhasil dihapus')
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWallets::route('/'),
        ];
    }
}
