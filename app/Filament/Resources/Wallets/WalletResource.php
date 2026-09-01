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
                TextInput::make('initial_balance')
                    ->label('Saldo Awal')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->dehydrated(false)
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Opsional. Jika diisi > 0, sistem otomatis mencatat transaksi saldo awal.'),
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
                \Filament\Actions\Action::make('adjustBalance')
                    ->label('Sesuaikan Saldo')
                    ->icon(Heroicon::AdjustmentsHorizontal)
                    ->color('warning')
                    ->iconButton()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() || auth()->user()?->hasPermission('manage_wallets'))
                    ->modalHeading(fn (Wallet $record): string => 'Sesuaikan Saldo Dompet: ' . $record->name)
                    ->modalDescription('Masukkan nilai saldo sebenarnya. Sistem akan otomatis membuat transaksi penyesuaian (pemasukan/pengeluaran) sesuai selisih saldo.')
                    ->mountUsing(function (\Filament\Actions\Action $action, ?Schema $schema, Wallet $record): void {
                        if (! $schema) {
                            return;
                        }

                        $schema->fill([
                            'current_balance' => number_format((float) $record->balance, 0, ',', '.'),
                            'new_balance' => (float) $record->balance,
                            'transaction_date' => now()->format('Y-m-d'),
                        ]);
                    })
                    ->schema([
                        TextInput::make('current_balance')
                            ->label('Saldo Saat Ini')
                            ->disabled()
                            ->dehydrated(false)
                            ->prefix('Rp'),
                        TextInput::make('new_balance')
                            ->label('Saldo Baru (Target)')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->helperText('Saldo riil/sebenarnya dari dompet ini.'),
                        \Filament\Forms\Components\Select::make('coa_id')
                            ->label('Akun COA Penyesuaian')
                            ->options(fn (): array =>
                                \App\Models\Coa::where('is_active', true)
                                    ->whereIn('category', ['pemasukan', 'pengeluaran'])
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn (\App\Models\Coa $coa): array => [
                                        $coa->id => '[' . ucfirst($coa->category) . '] ' . $coa->code . ' - ' . $coa->name,
                                    ])
                                    ->toArray()
                            )
                            ->searchable()
                            ->nullable()
                            ->helperText('Opsional. Jika kosong, sistem otomatis memakai akun default (Pendapatan Lain / Beban Operasional).'),
                        \Filament\Forms\Components\DatePicker::make('transaction_date')
                            ->label('Tanggal Transaksi')
                            ->required()
                            ->default(now()),
                        Textarea::make('description')
                            ->label('Keterangan Tambahan')
                            ->placeholder('Misal: Penyesuaian fisik kas / opname kas')
                            ->rows(2),
                    ])
                    ->action(function (\Filament\Actions\Action $action, array $data, Wallet $record): void {
                        $current = (float) $record->balance;
                        $new = (float) $data['new_balance'];
                        $diff = round($new - $current, 2);

                        if (abs($diff) < 0.01) {
                            Notification::make()
                                ->info()
                                ->title('Saldo tidak berubah')
                                ->body("Saldo dompet {$record->name} sudah sesuai dengan Rp " . number_format($new, 0, ',', '.'))
                                ->send();

                            return;
                        }

                        $date = $data['transaction_date'] ?? now()->format('Y-m-d');
                        $userNote = filled($data['description'] ?? null) ? ' (' . $data['description'] . ')' : '';

                        if ($diff > 0) {
                            $coaId = $data['coa_id']
                                ?? \App\Models\Coa::where('is_active', true)->where('category', 'pemasukan')->where('code', '4-2000')->value('id')
                                ?? \App\Models\Coa::where('is_active', true)->where('category', 'pemasukan')->first()?->id;

                            $desc = "Penyesuaian saldo dompet {$record->name} (+Rp " . number_format($diff, 0, ',', '.') . " : Rp " . number_format($current, 0, ',', '.') . " → Rp " . number_format($new, 0, ',', '.') . "){$userNote}";

                            \App\Models\Transaction::create([
                                'name' => 'Penyesuaian Saldo',
                                'user_id' => auth()->id(),
                                'wallet_id' => $record->id,
                                'coa_id' => $coaId,
                                'amount' => $diff,
                                'transaction_date' => $date,
                                'description' => $desc,
                            ]);
                        } else {
                            $diffAbs = abs($diff);
                            $coaId = $data['coa_id']
                                ?? \App\Models\Coa::where('is_active', true)->where('category', 'pengeluaran')->where('code', '5-2000')->value('id')
                                ?? \App\Models\Coa::where('is_active', true)->where('category', 'pengeluaran')->first()?->id;

                            $desc = "Penyesuaian saldo dompet {$record->name} (-Rp " . number_format($diffAbs, 0, ',', '.') . " : Rp " . number_format($current, 0, ',', '.') . " → Rp " . number_format($new, 0, ',', '.') . "){$userNote}";

                            \App\Models\Transaction::create([
                                'name' => 'Penyesuaian Saldo',
                                'user_id' => auth()->id(),
                                'wallet_id' => $record->id,
                                'coa_id' => $coaId,
                                'amount' => $diffAbs,
                                'transaction_date' => $date,
                                'description' => $desc,
                            ]);
                        }

                        Notification::make()
                            ->success()
                            ->title('Saldo berhasil disesuaikan')
                            ->body("Saldo dompet {$record->name} kini menjadi Rp " . number_format($new, 0, ',', '.') . " dan transaksi penyesuaian telah dicatat.")
                            ->send();
                    }),
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
