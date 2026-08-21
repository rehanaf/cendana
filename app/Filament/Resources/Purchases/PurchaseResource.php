<?php

namespace App\Filament\Resources\Purchases;

use App\Filament\Actions\BayarAction;
use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\Purchases\Pages\ManagePurchases;
use App\Models\Coa;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\Wallet;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $permissionPrefix = 'purchases';

    protected static ?string $model = Purchase::class;

    protected static ?string $recordTitleAttribute = 'invoice_no';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Pembelian';

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedShoppingBag;
    }

    public static function getNavigationLabel(): string
    {
        return 'Pembelian';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pembelian';
    }

    public static function getModelLabel(): string
    {
        return 'Pembelian';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('invoice_no')
                    ->label('No. Nota')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),
                DatePicker::make('due_date')
                    ->label('Jatuh Tempo')
                    ->default(now()),
                TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->required()
                    ->prefix('Rp'),
                Toggle::make('pay_now')
                    ->label('Langsung Lunas')
                    ->helperText('Buat transaksi pengeluaran penuh saat menyimpan')
                    ->default(false)
                    ->live()
                    ->hidden(fn ($record): bool => $record !== null),
                Select::make('wallet_id')
                    ->label('Dompet Pembayaran')
                    ->helperText('Dompet yang digunakan untuk transaksi lunas')
                    ->options(fn (): array => Wallet::where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray()
                    )
                    ->default(fn (): ?int => Setting::getWalletId('wallet_pembelian_id'))
                    ->searchable()
                    ->visible(fn (Get $get): bool => (bool) $get('pay_now')),
                Select::make('coa_id')
                    ->label('Akun Pengeluaran')
                    ->helperText('Akun untuk transaksi lunas')
                    ->options(fn (): array => Coa::where('is_active', true)
                        ->where('category', 'pengeluaran')
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code.' - '.$coa->name])
                        ->toArray()
                    )
                    ->default(fn (): ?int => (int) Setting::get('coa_pembelian_id') ?: null)
                    ->searchable()
                    ->visible(fn (Get $get): bool => (bool) $get('pay_now')),
                Textarea::make('notes')
                    ->label('Keterangan')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_no')
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('No. Nota')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d F Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d F Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('total_paid')
                    ->label('Dibayar')
                    ->money('IDR', decimalPlaces: 0)
                    ->toggleable(),
                TextColumn::make('sisa')
                    ->label('Sisa')
                    ->money('IDR', decimalPlaces: 0)
                    ->color(fn (Purchase $record): string => $record->sisa > 0 ? 'danger' : 'success')
                    ->toggleable(),
                TextColumn::make('notes')
                    ->label('Keterangan')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'lunas' => 'Lunas',
                        'berjalan' => 'Belum Lunas',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'lunas' => 'success',
                        'berjalan' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'berjalan' => 'Belum Lunas',
                        'lunas' => 'Lunas',
                    ]),
            ])
            ->recordActions([
                BayarAction::make('bayar')
                    ->linkColumn('purchase_id')
                    ->coaSettingKey('coa_pembelian_id')
                    ->walletSettingKey('wallet_pembelian_id')
                    ->namePrefix('Pembayaran')
                    ->coaCategory('pengeluaran')
                    ->visible(fn (Purchase $record): bool => $record->sisa > 0),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus yang Dipilih'),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePurchases::route('/'),
        ];
    }
}
