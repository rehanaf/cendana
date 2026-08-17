<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanDaftarPengeluaran extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Daftar Pengeluaran';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-daftar-pengeluaran';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-arrow-trending-down';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->total);

        return Grid::make(3)
            ->schema([
                $this->stat('Total Pengeluaran', $total, 'danger'),
                $this->statCount('Jumlah Transaksi', $rows->count()),
                $this->statCount('Jumlah Akun', $rows->where('kode', '!=', '')->unique('kode')->count()),
            ]);
    }

    protected function getQuery()
    {
        $query = Transaction::query()
            ->from('transactions as t')
            ->join('coas as c', 'c.id', '=', 't.coa_id')
            ->leftJoin('wallets as w', 'w.id', '=', 't.wallet_id')
            ->where('c.category', 'pengeluaran')
            ->select([
                't.id',
                't.transaction_date as date',
                DB::raw('COALESCE(NULLIF(t.description, \'\'), t.name) as keterangan'),
                'c.code as kode',
                'c.name as nama_akun',
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
                ->searchable()
                ->sortable(),
            TextColumn::make('kode')
                ->label('Kode Akun')
                ->searchable(),
            TextColumn::make('nama_akun')
                ->label('Akun')
                ->searchable(),
            TextColumn::make('dompet')
                ->label('Dompet')
                ->searchable(),
            TextColumn::make('total')
                ->label('Jumlah')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('danger')
                ->sortable(),
        ];
    }
}
