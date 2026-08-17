<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanPajakPenjualan extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Pajak Penjualan';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-pajak-penjualan';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-receipt-percent';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->total);

        return Grid::make(3)
            ->schema([
                $this->stat('Total Pajak', $total),
                $this->statCount('Jumlah Transaksi', $rows->count()),
                $this->statCount('Jenis Pajak', $rows->unique('kode')->count()),
            ]);
    }

    protected function getQuery()
    {
        $query = Transaction::query()
            ->from('transactions as t')
            ->join('coas as c', 'c.id', '=', 't.coa_id')
            ->leftJoin('wallets as w', 'w.id', '=', 't.wallet_id')
            ->where('c.type', 'tax')
            ->where('c.category', 'pemasukan')
            ->select([
                't.transaction_date as date',
                DB::raw('COALESCE(NULLIF(t.description, \'\'), t.name) as keterangan'),
                'c.code as kode',
                'c.name as nama_pajak',
                DB::raw('COALESCE(w.name, \'-\') as dompet'),
                't.amount as total',
            ])
            ->orderBy('t.transaction_date', 'desc');

        $query = $this->applyModeFilter($query, 't.transaction_date');

        return $query;
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('date')
                ->label('Tanggal')
                ->date('d F Y')
                ->sortable(),
            TextColumn::make('keterangan')
                ->label('Keterangan')
                ->searchable(),
            TextColumn::make('kode')
                ->label('Kode')
                ->searchable(),
            TextColumn::make('nama_pajak')
                ->label('Jenis Pajak')
                ->searchable(),
            TextColumn::make('dompet')
                ->label('Dompet'),
            TextColumn::make('total')
                ->label('Jumlah')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success')
                ->sortable(),
        ];
    }
}
