<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Purchase;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanDaftarPembelian extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Daftar Pembelian';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-daftar-pembelian';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-receipt-refund';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->total);
        $paid = $rows->sum(fn ($r) => (float) $r->paid);
        $sisa = $rows->sum(fn ($r) => (float) $r->sisa);

        return Grid::make(4)
            ->schema([
                $this->stat('Total Pembelian', $total),
                $this->stat('Total Dibayar', $paid, 'success'),
                $this->stat('Belum Dibayar', $sisa, $sisa > 0 ? 'danger' : 'success'),
                $this->statCount('Jumlah Nota', $rows->count()),
            ]);
    }

    protected function getQuery()
    {
        $query = Purchase::query()
            ->leftJoin('vendors as v', 'v.id', '=', 'purchases.vendor_id')
            ->select([
                'purchases.invoice_no as invoice_no',
                'purchases.date as date',
                DB::raw('COALESCE(v.name, \'\') as vendor_name'),
                'purchases.total as total',
                DB::raw('COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = purchases.id), 0) as paid'),
                DB::raw('purchases.total - COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = purchases.id), 0) as sisa'),
                'purchases.status as status',
            ])
            ->orderBy('purchases.date', 'desc');

        $query = $this->applyModeFilter($query, 'purchases.date');

        return $query;
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('invoice_no')
                ->label('No. Nota')
                ->searchable()
                ->sortable(),
            TextColumn::make('date')
                ->label('Tanggal')
                ->date('d F Y')
                ->sortable(),
            TextColumn::make('vendor_name')
                ->label('Vendor')
                ->searchable()
                ->sortable(),
            TextColumn::make('total')
                ->label('Total')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('paid')
                ->label('Dibayar')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success'),
            TextColumn::make('sisa')
                ->label('Sisa')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
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
        ];
    }
}
