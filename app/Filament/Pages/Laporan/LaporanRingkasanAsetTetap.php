<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanRingkasanAsetTetap extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Ringkasan Aset Tetap';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-ringkasan-aset-tetap';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-building-office';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->saldo);

        return Grid::make(3)
            ->schema([
                $this->stat('Nilai Aset Tetap', $total),
                $this->statCount('Jumlah Akun', $rows->count()),
                $this->statCount('Jumlah Transaksi', $rows->sum(fn ($r) => (int) $r->jumlah_transaksi)),
            ]);
    }

    protected function getQuery()
    {
        $query = Transaction::query()
            ->from('transactions as t')
            ->join('coas as c', 'c.id', '=', 't.coa_id')
            ->where('c.type', 'asset')
            ->where('c.category', 'pengeluaran')
            ->select([
                'c.code as kode',
                'c.name as nama',
                DB::raw('SUM(t.amount) as saldo'),
                DB::raw('COUNT(t.id) as jumlah_transaksi'),
            ])
            ->groupBy('c.id', 'c.code', 'c.name')
            ->orderBy('c.code');

        $query = $this->applyModeFilter($query, 't.transaction_date');

        return $query;
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('kode')
                ->label('Kode')
                ->searchable()
                ->sortable(),
            TextColumn::make('nama')
                ->label('Akun Aset')
                ->searchable()
                ->sortable(),
            TextColumn::make('saldo')
                ->label('Nilai')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('jumlah_transaksi')
                ->label('Transaksi')
                ->alignRight(),
        ];
    }
}
