<?php

namespace App\Filament\Pages\Laporan;

use App\Models\RetailInvoice;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanPenjualanPerProduk extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Penjualan Per Produk';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-penjualan-per-produk';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-cube';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->total);
        $count = $rows->sum(fn ($r) => (int) $r->jumlah_nota);

        return Grid::make(3)
            ->schema([
                $this->stat('Total Penjualan', $total),
                $this->statCount('Jumlah Transaksi', $count),
                $this->statCount('Jenis Produk', $rows->count()),
            ]);
    }

    protected function getQuery()
    {
        $query = RetailInvoice::query()
            ->from('retail_invoices as ri')
            ->leftJoin('internet_packages as ip', 'ip.id', '=', 'ri.internet_package_id')
            ->select([
                DB::raw('COALESCE(ip.name, \'Tanpa Paket\') as produk'),
                DB::raw('COUNT(*) as jumlah_nota'),
                DB::raw('SUM(ri.total) as total'),
                'ri.date',
            ])
            ->groupBy('ip.id', 'ip.name', 'ri.date')
            ->orderByDesc(DB::raw('SUM(ri.total)'));

        $query = $this->applyModeFilter($query, 'ri.date');

        return $query;
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('produk')
                ->label('Produk / Paket')
                ->searchable()
                ->sortable(),
            TextColumn::make('jumlah_nota')
                ->label('Jumlah Transaksi')
                ->alignRight()
                ->sortable(),
            TextColumn::make('total')
                ->label('Total Penjualan')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
        ];
    }
}
