<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilderContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LaporanPenjualan extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    public ?string $mode = 'harian';
    public ?string $date = null;
    public ?string $reportMonth = null;
    public ?string $reportYear = null;
    public ?string $periodStart = null;
    public ?string $periodEnd = null;
    public ?string $coaId = '';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Keuangan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return 'heroicon-o-banknotes';
    }

    public static function getNavigationLabel(): string
    {
        return 'Laporan Penjualan';
    }

    public function getTitle(): string
    {
        return 'Laporan Penjualan';
    }

    public static function getDefaultSlug(): string
    {
        return 'laporan-penjualan';
    }

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->reportMonth = now()->format('m');
        $this->reportYear = now()->format('Y');
        $this->periodStart = now()->startOfMonth()->format('Y-m-d');
        $this->periodEnd = now()->format('Y-m-d');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Laporan Penjualan')
                    ->afterHeader([
                        Select::make('coaId')
                            ->hiddenLabel()
                            ->native(true)
                            ->options(fn (): array =>
                                \App\Models\Coa::query()
                                    ->where('type', 'income')
                                    ->where('is_active', true)
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn (\App\Models\Coa $c): array => [$c->id => $c->code . ' - ' . $c->name])
                                    ->prepend('Semua Akun Penjualan', '')
                                    ->toArray()
                            )
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                        Select::make('mode')
                            ->hiddenLabel()
                            ->native(true)
                            ->options([
                                'harian' => 'Harian',
                                'bulanan' => 'Bulanan',
                                'periode' => 'Periode',
                            ])
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                        DatePicker::make('date')
                            ->hiddenLabel()
                            ->native(true)
                            ->visible(fn (): bool => $this->mode === 'harian')
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                        Select::make('reportMonth')
                            ->hiddenLabel()
                            ->native(true)
                            ->options([
                                '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                                '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                                '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                                '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
                            ])
                            ->visible(fn (): bool => $this->mode === 'bulanan')
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                        Select::make('reportYear')
                            ->hiddenLabel()
                            ->native(true)
                            ->options(fn (): array => collect(range(now()->year, now()->year - 5))
                                ->mapWithKeys(fn ($y) => [(string) $y => (string) $y])
                                ->toArray()
                            )
                            ->visible(fn (): bool => $this->mode === 'bulanan')
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                        DatePicker::make('periodStart')
                            ->hiddenLabel()
                            ->native(true)
                            ->visible(fn (): bool => $this->mode === 'periode')
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                        DatePicker::make('periodEnd')
                            ->hiddenLabel()
                            ->native(true)
                            ->visible(fn (): bool => $this->mode === 'periode')
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                    ])
                    ->schema([
                        View::make('components.report-loading')
                            ->viewData(['targets' => 'coaId, mode, date, reportMonth, reportYear, periodStart, periodEnd']),
                        $this->getStatsGrid(),
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getFilteredQuery()->get();

        $total = $rows->sum(fn ($row) => (float) $row->total);
        $totalPaid = $rows->sum(fn ($row) => (float) $row->total_paid);
        $sisa = $rows->sum(fn ($row) => max(0, (float) $row->total - (float) $row->total_paid));
        $count = $rows->count();

        return Grid::make(4)
            ->schema([
                Stat::make('Total Penjualan', 'Rp ' . number_format($total, 0, ',', '.')),
                Stat::make('Total Dibayar', 'Rp ' . number_format($totalPaid, 0, ',', '.')),
                Stat::make('Belum Dibayar', 'Rp ' . number_format($sisa, 0, ',', '.'))
                    ->color($sisa > 0 ? 'danger' : 'success'),
                Stat::make('Jumlah Nota', (string) $count),
            ]);
    }

    protected function salesSubQuery(): Builder
    {
        return DB::table('sales as s')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('coas as co', 'co.id', '=', 's.coa_id')
            ->select([
                's.invoice_no',
                's.date',
                DB::raw('COALESCE(c.name, \'\') as customer_name'),
                DB::raw('\'Penjualan\' as sumber'),
                's.coa_id',
                DB::raw('COALESCE(co.name, \'-\') as coa_name'),
                's.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sale_id = s.id) as total_paid'),
                's.status',
            ]);
    }

    protected function subscriptionSubQuery(): Builder
    {
        return DB::table('subscription_invoices as si')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 'si.customer_id')
            ->leftJoin('coas as co', 'co.id', '=', 'si.coa_id')
            ->select([
                'si.invoice_no',
                'si.date',
                DB::raw('COALESCE(c.name, \'\') as customer_name'),
                DB::raw('\'Langganan\' as sumber'),
                'si.coa_id',
                DB::raw('COALESCE(co.name, \'-\') as coa_name'),
                'si.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = si.id) as total_paid'),
                'si.status',
            ]);
    }

    protected function retailSubQuery(): Builder
    {
        return DB::table('retail_invoices as ri')
            ->leftJoin('retail_customers as rc', 'rc.id', '=', 'ri.retail_customer_id')
            ->leftJoin('coas as co', 'co.id', '=', 'ri.coa_id')
            ->select([
                'ri.invoice_no',
                'ri.date',
                DB::raw('COALESCE(rc.name, \'\') as customer_name'),
                DB::raw('\'Retail\' as sumber'),
                'ri.coa_id',
                DB::raw('COALESCE(co.name, \'-\') as coa_name'),
                'ri.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.retail_invoice_id = ri.id) as total_paid'),
                'ri.status',
            ]);
    }

    protected function otherIncomeSubQuery(): Builder
    {
        return DB::table('transactions as t')
            ->join('coas as co', 'co.id', '=', 't.coa_id')
            ->where('co.type', 'income')
            ->whereNull('t.sale_id')
            ->whereNull('t.purchase_id')
            ->whereNull('t.subscription_invoice_id')
            ->whereNull('t.retail_invoice_id')
            ->select([
                DB::raw('COALESCE(NULLIF(t.description, \'\'), CONCAT(\'Transaksi #\', t.id)) as invoice_no'),
                't.transaction_date as date',
                DB::raw('\'\' as customer_name'),
                DB::raw('\'Pendapatan Lain\' as sumber'),
                't.coa_id',
                DB::raw('COALESCE(co.name, \'-\') as coa_name'),
                't.amount as total',
                't.amount as total_paid',
                DB::raw('\'lunas\' as status'),
            ]);
    }

    protected function applyModeFilter(QueryBuilderContract $query): QueryBuilderContract
    {
        if ($this->mode === 'harian' && $this->date) {
            return $query->whereDate('date', $this->date);
        }

        if ($this->mode === 'bulanan') {
            return $query
                ->whereYear('date', (int) $this->reportYear)
                ->whereMonth('date', (int) $this->reportMonth);
        }

        if ($this->mode === 'periode' && $this->periodStart && $this->periodEnd) {
            return $query
                ->whereDate('date', '>=', $this->periodStart)
                ->whereDate('date', '<=', $this->periodEnd);
        }

        return $query;
    }

    protected function buildUnion(): Builder
    {
        $parts = [
            $this->salesSubQuery(),
            $this->subscriptionSubQuery(),
            $this->retailSubQuery(),
            $this->otherIncomeSubQuery(),
        ];

        $union = array_shift($parts);

        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return $union;
    }

    protected function getFilteredQuery(): QueryBuilderContract
    {
        $sub = $this->buildUnion();

        $query = \App\Models\Transaction::query()
            ->fromSub($sub, 'penjualan')
            ->select(['invoice_no', 'date', 'customer_name', 'sumber', 'coa_id', 'coa_name', 'total', 'total_paid', 'status']);

        if ($this->coaId) {
            $query->where('coa_id', (int) $this->coaId);
        }

        return $this->applyModeFilter($query);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn () => $this->getFilteredQuery())
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('No. Nota')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('coa_name')
                    ->label('Akun COA')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sumber')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => (string) $state)
                    ->color(fn ($state): string => match ($state) {
                        'Penjualan' => 'info',
                        'Langganan' => 'warning',
                        'Retail' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('total_paid')
                    ->label('Dibayar')
                    ->money('IDR', decimalPlaces: 0),
                TextColumn::make('sisa')
                    ->label('Sisa')
                    ->getStateUsing(fn ($record): float => max(0, (float) $record->total - (float) $record->total_paid))
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
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
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->defaultKeySort(false);
    }

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model | array $record): string
    {
        if (is_array($record)) {
            $record = (object) $record;
        }

        return ($record->sumber ?? '') . '|' . ($record->invoice_no ?? '');
    }
}