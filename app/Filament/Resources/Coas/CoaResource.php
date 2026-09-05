<?php

namespace App\Filament\Resources\Coas;

use App\Filament\Resources\Coas\Pages\ManageCoas;
use App\Models\Coa;
use App\Models\Purchase;
use App\Models\RetailInvoice;
use App\Models\Sale;
use App\Models\SubscriptionInvoice;
use App\Models\Transaction;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class CoaResource extends Resource
{
    protected static ?string $model = Coa::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    protected static string|\UnitEnum|null $navigationGroup = 'Kas';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return Heroicon::OutlinedBookOpen;
    }

    public static function getNavigationLabel(): string
    {
        return 'Chart of Account';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Chart of Account';
    }

    public static function getModelLabel(): string
    {
        return 'COA';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->hasPermission('view_coas'));
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')
                    ->label('Kode Akun')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Nama Akun')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Tipe')
                    ->options([
                        'asset' => 'Aset',
                        'liability' => 'Kewajiban',
                        'equity' => 'Modal',
                        'income' => 'Pendapatan',
                        'cogs' => 'HPP / Pembelian',
                        'expense' => 'Beban',
                        'tax' => 'Pajak',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $category = match ($get('type')) {
                            'income' => 'pemasukan',
                            'cogs', 'expense', 'tax' => 'pengeluaran',
                            default => null,
                        };
                        if ($category) {
                            $set('category', $category);
                        }
                    }),
                Select::make('category')
                    ->label('Kategori')
                    ->options([
                        'pemasukan' => 'Pemasukan',
                        'pengeluaran' => 'Pengeluaran',
                        'transfer' => 'Transfer',
                    ])
                    ->required(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Akun')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pemasukan' => 'Pemasukan',
                        'pengeluaran' => 'Pengeluaran',
                        'transfer' => 'Transfer',
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pemasukan' => 'success',
                        'pengeluaran' => 'danger',
                        'transfer' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'asset' => 'Aset',
                        'liability' => 'Kewajiban',
                        'equity' => 'Modal',
                        'income' => 'Pendapatan',
                        'cogs' => 'HPP / Pembelian',
                        'expense' => 'Beban',
                        'tax' => 'Pajak',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'asset' => 'info',
                        'liability' => 'warning',
                        'equity' => 'gray',
                        'income' => 'success',
                        'cogs' => 'warning',
                        'expense' => 'danger',
                        'tax' => 'danger',
                        default => 'gray',
                    }),
                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->tooltip('Hapus / pindahkan data ke COA pengganti')
                    ->form([
                        Select::make('replacement_coa_id')
                            ->label('COA Pengganti')
                            ->placeholder('Pilih COA pengganti…')
                            ->options(fn (DeleteAction $action): array => Coa::query()
                                ->where('id', '!=', $action->getRecord()?->getKey())
                                ->when($action->getRecord()?->category, fn ($q, $category) => $q->where('category', $category))
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code . ' - ' . $coa->name])
                                ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('Wajib diisi bila COA ini masih dipakai transaksi. Seluruh data akan dipindahkan ke COA pengganti, lalu COA dihapus.'),
                    ])
                    ->action(function (DeleteAction $action, Coa $record, array $data): void {
                        $usage = $record->usageLabels();

                        if ($usage->isNotEmpty()) {
                            $replacement = $data['replacement_coa_id'] ?? null;

                            if (! $replacement) {
                                Notification::make()
                                    ->danger()
                                    ->title('Pilih COA pengganti')
                                    ->body('COA ini masih dipakai oleh: ' . $usage->implode(', ') . '. Pilih COA pengganti untuk memindahkan datanya agar COA dapat dihapus.')
                                    ->persistent()
                                    ->send();

                                $action->halt();

                                return;
                            }

                            $target = Coa::find((int) $replacement);

                            if (! $target || $target->getKey() === $record->getKey()) {
                                Notification::make()
                                    ->danger()
                                    ->title('COA pengganti tidak valid')
                                    ->send();

                                $action->halt();

                                return;
                            }

                            DB::transaction(function () use ($record, $target): void {
                                static::reassignCoaData($record, $target);
                                $record->delete();
                            });

                            Notification::make()
                                ->success()
                                ->title('COA berhasil dihapus')
                                ->body("Data telah dipindahkan ke {$target->code} - {$target->name}.")
                                ->send();

                            return;
                        }

                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('COA berhasil dihapus')
                            ->send();
                    }),
            ])
            ->defaultSort('code');
    }

    public static function reassignCoaData(Coa $source, Coa $target): void
    {
        $affected = Transaction::query()
            ->where('coa_id', $source->getKey())
            ->get(['wallet_id', 'to_wallet_id'])
            ->flatMap(fn (Transaction $t): array => [$t->wallet_id, $t->to_wallet_id])
            ->filter()
            ->unique()
            ->values();

        $update = [
            'coa_id' => $target->getKey(),
            'updated_at' => now(),
        ];

        if ($target->category !== 'transfer') {
            $update['to_wallet_id'] = null;
        }

        Transaction::query()
            ->where('coa_id', $source->getKey())
            ->update($update);

        Sale::query()->where('coa_id', $source->getKey())->update(['coa_id' => $target->getKey()]);
        Purchase::query()->where('coa_id', $source->getKey())->update(['coa_id' => $target->getKey()]);
        RetailInvoice::query()->where('coa_id', $source->getKey())->update(['coa_id' => $target->getKey()]);
        SubscriptionInvoice::query()->where('coa_id', $source->getKey())->update(['coa_id' => $target->getKey()]);

        if ($affected->isNotEmpty()) {
            Transaction::recalculateWalletBalances($affected->all());
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCoas::route('/'),
        ];
    }
}
