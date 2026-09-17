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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

    public static function unifiedQuery(): Builder
    {
        $offset = 1000000000;

        $sales = DB::table('sales as s')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 's.customer_id')
            ->select([
                's.id as id',
                's.invoice_no as invoice_no',
                's.date as date',
                's.due_date as due_date',
                DB::raw('COALESCE(c.customer_code, \'\') as customer_code'),
                DB::raw('COALESCE(c.name, \'\') as customer_name'),
                DB::raw('\'Corporate\' as customer_type'),
                DB::raw('0 as is_retail'),
                's.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sale_id = s.id) as paid'),
                's.status as status',
                DB::raw('COALESCE(s.notes, \'\') as notes'),
                's.id as sale_id',
                DB::raw('NULL as retail_invoice_id'),
                DB::raw('NULL as period'),
                's.customer_id as customer_id',
                DB::raw('COALESCE(s.marketing_cost, 0) as marketing_cost'),
                's.coa_id as coa_id',
                's.wallet_id as wallet_id',
            ]);

        $retail = DB::table('retail_invoices as ri')
            ->leftJoin('retail_customers as rc', 'rc.id', '=', 'ri.retail_customer_id')
            ->select([
                DB::raw("$offset + ri.id as id"),
                'ri.invoice_no as invoice_no',
                'ri.date as date',
                'ri.due_date as due_date',
                DB::raw('COALESCE(rc.customer_code, \'\') as customer_code'),
                DB::raw('COALESCE(rc.name, \'\') as customer_name'),
                DB::raw('\'Retail\' as customer_type'),
                DB::raw('1 as is_retail'),
                'ri.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.retail_invoice_id = ri.id) as paid'),
                'ri.status as status',
                DB::raw('COALESCE(ri.notes, \'\') as notes'),
                DB::raw('NULL as sale_id'),
                'ri.id as retail_invoice_id',
                'ri.period as period',
                DB::raw('NULL as customer_id'),
                DB::raw('0 as marketing_cost'),
                'ri.coa_id as coa_id',
                'ri.wallet_id as wallet_id',
            ]);

        $union = $sales->unionAll($retail);

        $query = (new Sale)
            ->newQuery()
            ->fromSub($union, 'penjualan')
            ->select([
                'id',
                'invoice_no',
                'date',
                'due_date',
                'customer_code',
                'customer_name',
                'customer_type',
                'is_retail',
                'total',
                'paid',
                'status',
                'notes',
                'sale_id',
                'retail_invoice_id',
                'period',
                'customer_id',
                'marketing_cost',
                'coa_id',
                'wallet_id',
            ]);

        $user = auth()->user();

        if ($user && ! $user->isAdmin() && ! $user->hasPermission('view_retail_invoices')) {
            $query->where('is_retail', 0);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('invoice_no')
                    ->label('No. Nota')
                    ->required()
                    ->scopedUnique(Sale::class, 'invoice_no', ignoreRecord: true)
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
                    ->label('Akun Pemasukan / COA')
                    ->helperText('Akun penjualan untuk nota ini.')
                    ->options(fn (): array => Coa::where('is_active', true)
                        ->where('category', 'pemasukan')
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code.' - '.$coa->name])
                        ->toArray()
                    )
                    ->default(fn (): ?int => (int) Setting::get('coa_penjualan_id') ?: null)
                    ->searchable(),
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
            ->query(static::unifiedQuery())
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
                TextColumn::make('customer_code')
                    ->label('ID Pelanggan')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('customer_name')
                    ->label('Nama Pelanggan')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('customer_type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Retail' ? 'success' : 'info')
                    ->toggleable(),
                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d F Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('period')
                    ->label('Periode')
                    ->date('F Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(fn (Sale $record): ?string => $record->period ?: null),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('marketing_cost')
                    ->label('Biaya Marketing')
                    ->money('IDR', decimalPlaces: 0)
                    ->placeholder('-')
                    ->toggleable()
                    ->visible(fn (?Sale $record): bool => $record !== null && ! (int) $record->is_retail),
                TextColumn::make('paid')
                    ->label('Dibayar')
                    ->money('IDR', decimalPlaces: 0)
                    ->toggleable(),
                TextColumn::make('sisa')
                    ->label('Sisa')
                    ->money('IDR', decimalPlaces: 0)
                    ->getStateUsing(fn (Sale $record): float => max(0, (float) $record->total - (float) $record->paid))
                    ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success')
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
                SelectFilter::make('customer_type')
                    ->label('Tipe Pelanggan')
                    ->options([
                        'Corporate' => 'Corporate',
                        'Retail' => 'Retail',
                    ]),
            ])
            ->recordActions([
                BayarAction::make('bayar')
                    ->linkColumn('sale_id')
                    ->coaSettingKey('coa_penjualan_id')
                    ->walletSettingKey('wallet_penjualan_id')
                    ->namePrefix('Pembayaran')
                    ->coaCategory('pemasukan')
                    ->visible(fn (Sale $record): bool => ! (int) $record->is_retail && max(0, (float) $record->total - (float) $record->paid) > 0),
                BayarAction::make('bayar_retail')
                    ->linkColumn('retail_invoice_id')
                    ->coaSettingKey('coa_retail_id')
                    ->walletSettingKey('wallet_retail_id')
                    ->namePrefix('Pembayaran Retail')
                    ->coaCategory('pemasukan')
                    ->visible(fn (Sale $record): bool => (int) $record->is_retail === 1 && max(0, (float) $record->total - (float) $record->paid) > 0),
                CetakInvoiceAction::make()
                    ->type('sale')
                    ->visible(fn (Sale $record): bool => ! (int) $record->is_retail),
                CetakInvoiceAction::make('cetak_retail')
                    ->type('retail')
                    ->keyColumn('retail_invoice_id')
                    ->visible(fn (Sale $record): bool => (int) $record->is_retail === 1),
                EditAction::make()->iconButton()
                    ->visible(fn (Sale $record): bool => ! (int) $record->is_retail),
                DeleteAction::make()->iconButton()
                    ->visible(fn (Sale $record): bool => ! (int) $record->is_retail),
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
            ->defaultSort('date', 'desc')
            ->defaultKeySort(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSales::route('/'),
        ];
    }
}
