<?php

namespace App\Filament\Resources\RetailInvoices;

use App\Filament\Actions\BayarAction;
use App\Filament\Actions\BayarGabunganAction;
use App\Filament\Actions\CetakInvoiceAction;
use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\RetailInvoices\Pages\ManageRetailInvoices;
use App\Models\Coa;
use App\Models\PelangganRetail;
use App\Models\RetailInvoice;
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

class RetailInvoiceResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $permissionPrefix = 'retail_invoices';

    protected static ?string $model = RetailInvoice::class;

    protected static ?string $recordTitleAttribute = 'invoice_no';

    protected static ?int $navigationSort = 4;

    protected static string|\UnitEnum|null $navigationGroup = 'Penjualan';

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedCalendarDays;
    }

    public static function getNavigationLabel(): string
    {
        return 'Tagihan Pelanggan Retail';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tagihan Pelanggan Retail';
    }

    public static function getModelLabel(): string
    {
        return 'Tagihan Pelanggan Retail';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('invoice_no')
                    ->label('No. Invoice')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('retail_customer_id')
                    ->label('Pelanggan')
                    ->relationship('customer', 'name')
                    ->getOptionLabelFromRecordUsing(fn (PelangganRetail $record): string => $record->customer_code.' - '.$record->name)
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('internet_package_id')
                    ->label('Paket Internet')
                    ->relationship('paketInternet', 'name')
                    ->searchable()
                    ->preload(),
                DatePicker::make('period')
                    ->label('Periode')
                    ->required()
                    ->displayFormat('F Y')
                    ->default(now()->startOfMonth()),
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
                    ->default(fn (): ?int => Setting::getWalletId('wallet_retail_id'))
                    ->searchable()
                    ->visible(fn (Get $get): bool => (bool) $get('pay_now')),
                Select::make('coa_id')
                    ->label('Akun Pemasukan / COA')
                    ->helperText('Akun penjualan retail untuk nota ini.')
                    ->options(fn (): array => Coa::where('is_active', true)
                        ->where('category', 'pemasukan')
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code.' - '.$coa->name])
                        ->toArray()
                    )
                    ->default(fn (): ?int => (int) Setting::get('coa_retail_id') ?: null)
                    ->searchable(),
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
                    ->label('No. Invoice')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('paketInternet.name')
                    ->label('Paket')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Periode')
                    ->date('F Y')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('total_paid')
                    ->label('Dibayar')
                    ->money('IDR', decimalPlaces: 0),
                TextColumn::make('sisa')
                    ->label('Sisa')
                    ->money('IDR', decimalPlaces: 0)
                    ->color(fn (RetailInvoice $record): string => $record->sisa > 0 ? 'danger' : 'success'),
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
                    }),
            ])
            ->filters([
                SelectFilter::make('internet_package_id')
                    ->label('Paket Internet')
                    ->relationship('paketInternet', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'berjalan' => 'Belum Lunas',
                        'lunas' => 'Lunas',
                    ]),
            ])
            ->recordActions([
                BayarAction::make('bayar')
                    ->linkColumn('retail_invoice_id')
                    ->coaSettingKey('coa_retail_id')
                    ->walletSettingKey('wallet_retail_id')
                    ->namePrefix('Pembayaran Retail')
                    ->coaCategory('pemasukan')
                    ->visible(fn (RetailInvoice $record): bool => $record->sisa > 0),
                CetakInvoiceAction::make()->type('retail'),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BayarGabunganAction::make()
                        ->linkColumn('retail_invoice_id')
                        ->coaSettingKey('coa_retail_id')
                        ->walletSettingKey('wallet_retail_id')
                        ->namePrefix('Pembayaran Retail')
                        ->coaCategory('pemasukan'),
                    DeleteBulkAction::make()
                        ->label('Hapus yang Dipilih'),
                ]),
            ])
            ->defaultSort('period', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRetailInvoices::route('/'),
        ];
    }
}
