<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanDetailAsetTetap extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Detail Aset Tetap';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-detail-aset-tetap';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->amount);

        return Grid::make(3)
            ->schema([
                $this->stat('Total Transaksi Aset', $total),
                $this->statCount('Jumlah Transaksi', $rows->count()),
                $this->statCount('Jumlah Akun', $rows->unique('kode')->count()),
            ]);
    }

    protected function getQuery()
    {
        $query = Transaction::query()
            ->from('transactions as t')
            ->join('coas as c', 'c.id', '=', 't.coa_id')
            ->leftJoin('wallets as w', 'w.id', '=', 't.wallet_id')
            ->where('c.type', 'asset')
            ->where('c.category', 'pengeluaran')
            ->select([
                't.transaction_date as date',
                DB::raw('COALESCE(NULLIF(t.description, \'\'), t.name) as keterangan'),
                'c.code as kode',
                'c.name as nama_akun',
                DB::raw('COALESCE(w.name, \'-\') as dompet'),
                't.amount as amount',
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
                ->label('Kode Akun')
                ->searchable(),
            TextColumn::make('nama_akun')
                ->label('Akun Aset')
                ->searchable(),
            TextColumn::make('dompet')
                ->label('Dompet'),
            TextColumn::make('amount')
                ->label('Jumlah')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
        ];
    }
}
