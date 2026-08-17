<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Purchase;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanPembelianPerSupplier extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Pembelian Per Supplier';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-pembelian-per-supplier';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-truck';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->total);
        $paid = $rows->sum(fn ($r) => (float) $r->dibayar);
        $sisa = $rows->sum(fn ($r) => (float) $r->sisa);

        return Grid::make(4)
            ->schema([
                $this->stat('Total Pembelian', $total),
                $this->stat('Total Dibayar', $paid, 'success'),
                $this->stat('Belum Dibayar', $sisa, $sisa > 0 ? 'danger' : 'success'),
                $this->statCount('Jumlah Supplier', $rows->count()),
            ]);
    }

    protected function getQuery()
    {
        $purchases = DB::table('purchases as p')
            ->leftJoin('vendors as v', 'v.id', '=', 'p.vendor_id')
            ->select([
                'p.id',
                'p.date',
                DB::raw('COALESCE(v.name, \'-\') as nama'),
                'p.total',
                DB::raw('COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = p.id), 0) as dibayar'),
            ]);

        $query = Purchase::query()
            ->fromSub($purchases, 'pb')
            ->select([
                'nama',
                DB::raw('COUNT(*) as jumlah_nota'),
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(dibayar) as dibayar'),
                DB::raw('SUM(total) - SUM(dibayar) as sisa'),
            ])
            ->groupBy('nama');

        $query = $this->applyModeFilter($query, 'date');

        return $query;
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('nama')
                ->label('Supplier')
                ->searchable()
                ->sortable(),
            TextColumn::make('jumlah_nota')
                ->label('Jumlah Nota')
                ->alignRight()
                ->sortable(),
            TextColumn::make('total')
                ->label('Total')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('dibayar')
                ->label('Dibayar')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success'),
            TextColumn::make('sisa')
                ->label('Sisa')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
        ];
    }
}
