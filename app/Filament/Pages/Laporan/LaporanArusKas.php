<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanArusKas extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Arus Kas';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-arus-kas';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-banknotes';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $masuk = $rows->sum(fn ($r) => (float) $r->masuk);
        $keluar = $rows->sum(fn ($r) => (float) $r->keluar);
        $transfer = $rows->sum(fn ($r) => (float) $r->transfer);

        return Grid::make(4)
            ->schema([
                $this->stat('Kas Masuk', $masuk, 'success'),
                $this->stat('Kas Keluar', $keluar, 'danger'),
                $this->stat('Transfer Kas', $transfer, 'warning'),
                $this->stat('Arus Kas Bersih', $masuk - $keluar, $masuk - $keluar >= 0 ? 'success' : 'danger'),
            ]);
    }

    protected function getQuery()
    {
        $query = Transaction::query()
            ->from('transactions as t')
            ->join('coas as c', 'c.id', '=', 't.coa_id')
            ->whereIn('c.category', ['pemasukan', 'pengeluaran', 'transfer'])
            ->select([
                'c.code as kode',
                'c.name as nama',
                DB::raw('COALESCE(SUM(CASE WHEN c.category = \'pemasukan\' THEN t.amount ELSE 0 END), 0) as masuk'),
                DB::raw('COALESCE(SUM(CASE WHEN c.category = \'pengeluaran\' THEN t.amount ELSE 0 END), 0) as keluar'),
                DB::raw('COALESCE(SUM(CASE WHEN c.category = \'transfer\' THEN t.amount ELSE 0 END), 0) as transfer'),
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
                ->label('Akun')
                ->searchable()
                ->sortable(),
            TextColumn::make('masuk')
                ->label('Kas Masuk')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success')
                ->sortable(),
            TextColumn::make('keluar')
                ->label('Kas Keluar')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('danger')
                ->sortable(),
            TextColumn::make('transfer')
                ->label('Transfer')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('warning')
                ->sortable(),
            TextColumn::make('bersih')
                ->label('Bersih')
                ->getStateUsing(fn ($record): float => (float) $record->masuk - (float) $record->keluar)
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($state): string => (float) $state >= 0 ? 'success' : 'danger'),
        ];
    }
}
