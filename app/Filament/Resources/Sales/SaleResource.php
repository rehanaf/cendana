<?php

namespace App\Filament\Resources\Sales;

use App\Filament\Actions\BayarAction;
use App\Filament\Actions\BayarGabunganAction;
use App\Filament\Actions\CetakInvoiceAction;
use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\Sales\Pages\ManageSales;
use App\Models\Coa;
use App\Models\PelangganCorporate;
use App\Models\Sale;
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

class SaleResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $permissionPrefix = 'sales';

    protected static ?string $model = Sale::class;

    protected static ?string $recordTitleAttribute = 'invoice_no';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Penjualan';

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedShoppingCart;
    }

    public static function getNavigationLabel(): string
    {
        return 'Penjualan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Penjualan';
    }

    public static function getModelLabel(): string
    {
        return 'Penjualan';
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
                Select::make('customer_id')
                    ->label('Pelanggan')
                    ->relationship('customer', 'name')
                    ->getOptionLabelFromRecordUsing(fn (PelangganCorporate $record): string => $record->customer_code.' - '.$record->name)
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),
                DatePicker::make('due_date')
                    ->label('Jatuh Tempo')
                    ->required()
                    ->default(now()),
                TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->required()
                    ->prefix('Rp'),
                Toggle::make('pay_now')
                    ->label('Langsung Lunas')
                    ->helperText('Buat transaksi pemasukan penuh saat menyimpan')
                    ->default(false)
                    ->live()
                    ->hidden(fn ($record): bool => $record !== null),
                Select::make('wallet_id')
                    ->label('Dompet Pembayaran')
                    ->helperText('Dompet yang digunakan untuk transaksi lunas')
                    ->options(fn (): array => Wallet::where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray()
                    )
                    ->default(fn (): ?int => Setting::getWalletId('wallet_penjualan_id'))
                    ->searchable()
                    ->visible(fn (Get $get): bool => (bool) $get('pay_now')),
                Select::make('coa_id')
                    ->label('Akun Pemasukan')
                    ->helperText('Akun untuk transaksi lunas')
                    ->options(fn (): array => Coa::where('is_active', true)
                        ->where('category', 'pemasukan')
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code.' - '.$coa->name])
                        ->toArray()
                    )
                    ->default(fn (): ?int => (int) Setting::get('coa_penjualan_id') ?: null)
                    ->searchable()
                    ->visible(fn (Get $get): bool => (bool) $get('pay_now')),
                TextInput::make('marketing_cost')
                    ->label('Biaya Marketing')
                    ->numeric()
                    ->default(0)
                    ->prefix('Rp'),
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
                TextColumn::make('customer.customer_code')
                    ->label('ID Pelanggan')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('customer.name')
                    ->label('Nama Pelanggan')
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
                    ->color(fn (Sale $record): string => $record->sisa > 0 ? 'danger' : 'success')
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
                        'tak_tertagih' => 'Tak Tertagih',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'lunas' => 'success',
                        'berjalan' => 'warning',
                        'tak_tertagih' => 'danger',
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
                        'tak_tertagih' => 'Tak Tertagih',
                    ]),
            ])
            ->recordActions([
                BayarAction::make('bayar')
                    ->linkColumn('sale_id')
                    ->coaSettingKey('coa_penjualan_id')
                    ->walletSettingKey('wallet_penjualan_id')
                    ->namePrefix('Pembayaran')
                    ->coaCategory('pemasukan')
                    ->visible(fn (Sale $record): bool => $record->sisa > 0),
                CetakInvoiceAction::make()->type('sale'),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BayarGabunganAction::make()
                        ->linkColumn('sale_id')
                        ->coaSettingKey('coa_penjualan_id')
                        ->walletSettingKey('wallet_penjualan_id')
                        ->namePrefix('Pembayaran')
                        ->coaCategory('pemasukan'),
                    DeleteBulkAction::make()
                        ->label('Hapus yang Dipilih'),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSales::route('/'),
        ];
    }
}
