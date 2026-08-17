<?php

namespace App\Filament\Pages;

use App\Models\Sale;
use App\Models\SubscriptionInvoice;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class LaporanPiutang extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static ?int $navigationSort = 5;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Keuangan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return 'heroicon-o-arrow-trending-up';
    }

    public static function getNavigationLabel(): string
    {
        return 'Laporan Piutang';
    }

    public function getTitle(): string
    {
        return 'Laporan Piutang';
    }

    public static function getDefaultSlug(): string
    {
        return 'laporan-piutang';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Laporan Piutang')
                    ->description('Daftar penjualan dan tagihan langganan yang belum lunas')
                    ->schema([
                        $this->getStatsGrid(),
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    public function getStatsGrid(): Grid
    {
        $records = $this->getOutstandingQuery()->get();

        $totalSisa = $records->sum(fn ($row): float => (float) $row->sisa_bayar);
        $sisaPenjualan = $records->where('source', 'Penjualan')->sum(fn ($row): float => (float) $row->sisa_bayar);
        $sisaLangganan = $records->where('source', 'Langganan')->sum(fn ($row): float => (float) $row->sisa_bayar);

        return Grid::make(4)
            ->schema([
                Stat::make('Total Piutang', 'Rp ' . number_format($totalSisa, 0, ',', '.'))
                    ->color($totalSisa > 0 ? 'danger' : 'success'),
                Stat::make('Piutang Penjualan', 'Rp ' . number_format($sisaPenjualan, 0, ',', '.'))
                    ->color($sisaPenjualan > 0 ? 'danger' : 'success'),
                Stat::make('Piutang Langganan', 'Rp ' . number_format($sisaLangganan, 0, ',', '.'))
                    ->color($sisaLangganan > 0 ? 'danger' : 'success'),
                Stat::make('Jumlah Tagihan', (string) $records->count()),
            ]);
    }

    protected function getOutstandingQuery()
    {
        $sales = Sale::query()
            ->join('corporate_customers', 'corporate_customers.id', '=', 'sales.customer_id')
            ->select([
                'sales.id as id',
                'sales.invoice_no as invoice_no',
                DB::raw("'Penjualan' as source"),
                'corporate_customers.name as customer_name',
                'sales.date as date',
                'sales.total as total',
                DB::raw('COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.sale_id = sales.id), 0) as paid'),
                DB::raw('sales.total - COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.sale_id = sales.id), 0) as sisa_bayar'),
            ])
            ->whereRaw('sales.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.sale_id = sales.id), 0)');

        $langganan = SubscriptionInvoice::query()
            ->join('corporate_customers', 'corporate_customers.id', '=', 'subscription_invoices.customer_id')
            ->select([
                'subscription_invoices.id as id',
                'subscription_invoices.invoice_no as invoice_no',
                DB::raw("'Langganan' as source"),
                'corporate_customers.name as customer_name',
                'subscription_invoices.date as date',
                'subscription_invoices.total as total',
                DB::raw('COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.subscription_invoice_id = subscription_invoices.id), 0) as paid'),
                DB::raw('subscription_invoices.total - COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.subscription_invoice_id = subscription_invoices.id), 0) as sisa_bayar'),
            ])
            ->whereRaw('subscription_invoices.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.subscription_invoice_id = subscription_invoices.id), 0)');

        return $sales->union($langganan);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn () => $this->getOutstandingQuery())
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'Penjualan' ? 'Penjualan' : 'Langganan')
                    ->color(fn (string $state): string => $state === 'Penjualan' ? 'info' : 'warning'),
                TextColumn::make('invoice_no')
                    ->label('No. Invoice'),
                TextColumn::make('customer_name')
                    ->label('Pelanggan'),
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                TextColumn::make('paid')
                    ->label('Dibayar')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                TextColumn::make('sisa_bayar')
                    ->label('Sisa')
                    ->color('danger')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
            ])
            ->defaultSort('date', 'desc');
    }
}