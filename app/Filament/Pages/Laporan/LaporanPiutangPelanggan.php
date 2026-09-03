<?php

namespace App\Filament\Pages\Laporan;

use App\Filament\Pages\Concerns\HasReportFilters;
use App\Models\Transaction;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LaporanPiutangPelanggan extends Page implements HasTable
{
    use HasReportFilters;
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return 'Piutang Pelanggan';
    }

    public function getTitle(): string
    {
        return 'Piutang Pelanggan';
    }

    public static function getDefaultSlug(): string
    {
        return 'laporan-piutang-pelanggan';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-arrow-trending-up';
    }

    public function mount(): void
    {
        $this->mountReportFilters();
        $this->mode = 'semua';
    }

    protected function reportModeSelect(): Select
    {
        return Select::make('mode')
            ->hiddenLabel()
            ->native(true)
            ->options([
                'semua' => 'Semua',
                'harian' => 'Harian',
                'bulanan' => 'Bulanan',
                'periode' => 'Periode',
            ])
            ->live()
            ->afterStateUpdated(fn () => $this->dispatch('refresh-table'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Piutang Pelanggan')
                    ->afterHeader($this->reportFilterComponents())
                    ->schema([
                        $this->getStatsGrid(),
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getOutstandingQuery()->get();

        $totalPiutang = $rows->sum(fn ($row): float => (float) $row->total);
        $totalSisa = $rows->sum(fn ($row): float => (float) $row->sisa_bayar);
        $sisaPenjualan = $rows->where('source', 'Penjualan')->sum(fn ($row): float => (float) $row->sisa_bayar);
        $sisaLangganan = $rows->where('source', 'Langganan')->sum(fn ($row): float => (float) $row->sisa_bayar);
        $sisaRetail = $rows->where('source', 'Retail')->sum(fn ($row): float => (float) $row->sisa_bayar);

        return Grid::make(5)
            ->schema([
                Stat::make('Total Piutang', 'Rp '.number_format($totalPiutang, 0, ',', '.'))
                    ->color($totalPiutang > 0 ? 'danger' : 'success'),
                Stat::make('Total Tagihan', 'Rp '.number_format($totalSisa, 0, ',', '.'))
                    ->color($totalSisa > 0 ? 'danger' : 'success'),
                Stat::make('Piutang Penj. Lain-Lain', 'Rp '.number_format($sisaPenjualan, 0, ',', '.'))
                    ->color($sisaPenjualan > 0 ? 'danger' : 'success'),
                Stat::make('Piutang Penj Corporate Bulanan', 'Rp '.number_format($sisaLangganan, 0, ',', '.'))
                    ->color($sisaLangganan > 0 ? 'danger' : 'success'),
                Stat::make('Piutang Penj. Retail Bulanan', 'Rp '.number_format($sisaRetail, 0, ',', '.'))
                    ->color($sisaRetail > 0 ? 'danger' : 'success'),
            ]);
    }

    protected function salesSub()
    {
        return DB::table('sales as s')
            ->join('corporate_customers as c', 'c.id', '=', 's.customer_id')
            ->select([
                's.invoice_no as invoice_no',
                DB::raw("'Penjualan' as source"),
                'c.name as customer_name',
                's.date as date',
                's.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sale_id = s.id) as paid'),
            ])
            ->whereRaw('s.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.sale_id = s.id), 0)');
    }

    protected function langgananSub()
    {
        return DB::table('subscription_invoices as si')
            ->join('corporate_customers as c', 'c.id', '=', 'si.customer_id')
            ->select([
                'si.invoice_no as invoice_no',
                DB::raw("'Langganan' as source"),
                'c.name as customer_name',
                'si.date as date',
                'si.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = si.id) as paid'),
            ])
            ->whereRaw('si.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.subscription_invoice_id = si.id), 0)');
    }

    protected function retailSub()
    {
        return DB::table('retail_invoices as ri')
            ->join('retail_customers as rc', 'rc.id', '=', 'ri.retail_customer_id')
            ->select([
                'ri.invoice_no as invoice_no',
                DB::raw("'Retail' as source"),
                'rc.name as customer_name',
                'ri.date as date',
                'ri.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.retail_invoice_id = ri.id) as paid'),
            ])
            ->whereRaw('ri.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.retail_invoice_id = ri.id), 0)');
    }

    protected function getOutstandingQuery()
    {
        $union = $this->salesSub()
            ->unionAll($this->langgananSub())
            ->unionAll($this->retailSub());

        return Transaction::query()
            ->fromSub($union, 'piutang')
            ->select([
                'invoice_no',
                'source',
                'customer_name',
                'date',
                'total',
                'paid',
                DB::raw('total - paid as sisa_bayar'),
            ])
            ->orderByDesc('date');
    }

    protected function getTableQuery()
    {
        return $this->applyModeFilter($this->getOutstandingQuery(), 'date');
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn () => $this->getTableQuery())
            ->defaultKeySort(false)
            ->filters([
                SelectFilter::make('source')
                    ->label('Sumber')
                    ->options([
                        'Penjualan' => 'Penjualan',
                        'Langganan' => 'Langganan Corporate',
                        'Retail' => 'Langganan Retail',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('source', $data['value'])
                        : $query),
            ])
            ->columns([
                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'Penjualan' => 'Penjualan',
                        'Langganan' => 'Langganan Corporate',
                        'Retail' => 'Langganan Retail',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Penjualan' => 'info',
                        'Langganan' => 'warning',
                        'Retail' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('invoice_no')
                    ->label('No. Invoice')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.')),
                TextColumn::make('paid')
                    ->label('Dibayar')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.')),
                TextColumn::make('sisa_bayar')
                    ->label('Sisa')
                    ->color('danger')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.')),
            ]);
    }

    public function getTableRecordKey(Model | array $record): string
    {
        if (is_array($record)) {
            $record = (object) $record;
        }

        return ($record->source ?? '').'|'.($record->invoice_no ?? '');
    }
}
