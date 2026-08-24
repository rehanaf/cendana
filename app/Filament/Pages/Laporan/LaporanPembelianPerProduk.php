<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Purchase;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanPembelianPerProduk extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Pembelian Per Produk';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-pembelian-per-produk';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-cube';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->total);

        return Grid::make(3)
            ->schema([
                $this->stat('Total Pembelian', $total),
                $this->statCount('Jumlah Nota', $rows->sum(fn ($r) => (int) $r->jumlah_nota)),
                $this->statCount('Jenis Produk', $rows->count()),
            ]);
    }

    protected function getQuery()
    {
        $query = Purchase::query()
            ->from('purchases as p')
            ->leftJoin('coas as c', 'c.id', '=', 'p.coa_id')
            ->select([
                DB::raw('COALESCE(c.name, \'Tanpa Akun\') as produk'),
                DB::raw('COUNT(*) as jumlah_nota'),
                DB::raw('SUM(p.total) as total'),
            ])
            ->groupBy('c.id', 'c.name')
            ->orderByDesc(DB::raw('SUM(p.total)'));

        $query = $this->applyModeFilter($query, 'p.date');

        return $query;
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('produk')
                ->label('Produk / Akun')
                ->searchable()
                ->sortable(),
            TextColumn::make('jumlah_nota')
                ->label('Jumlah Nota')
                ->alignRight()
                ->sortable(),
            TextColumn::make('total')
                ->label('Total Pembelian')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
        ];
    }
}
