<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanLabaRugi extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Laba Rugi';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-laba-rugi';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-chart-bar';
    }

    public function getStatsGrid(): Grid
    {
        $income = (float) $this->incomeQuery()->sum('t.amount');
        $expense = (float) $this->expenseQuery()->sum('t.amount');
        $laba = $income - $expense;

        return Grid::make(3)
            ->schema([
                $this->stat('Pendapatan', $income, 'success'),
                $this->stat('Beban', $expense, 'danger'),
                $this->stat('Laba / Rugi', $laba, $laba >= 0 ? 'success' : 'danger'),
            ]);
    }

    protected function incomeQuery()
    {
        return Transaction::query()
            ->from('transactions as t')
            ->join('coas as c', 'c.id', '=', 't.coa_id')
            ->where('c.category', 'pemasukan')
            ->when($this->mode === 'harian' && $this->date, fn ($q) => $q->whereDate('t.transaction_date', $this->date))
            ->when($this->mode === 'bulanan', fn ($q) => $q
                ->whereYear('t.transaction_date', (int) $this->reportYear)
                ->whereMonth('t.transaction_date', (int) $this->reportMonth))
            ->when($this->mode === 'periode' && $this->periodStart && $this->periodEnd, fn ($q) => $q
                ->whereDate('t.transaction_date', '>=', $this->periodStart)
                ->whereDate('t.transaction_date', '<=', $this->periodEnd));
    }

    protected function expenseQuery()
    {
        return Transaction::query()
            ->from('transactions as t')
            ->join('coas as c', 'c.id', '=', 't.coa_id')
            ->where('c.category', 'pengeluaran')
            ->when($this->mode === 'harian' && $this->date, fn ($q) => $q->whereDate('t.transaction_date', $this->date))
            ->when($this->mode === 'bulanan', fn ($q) => $q
                ->whereYear('t.transaction_date', (int) $this->reportYear)
                ->whereMonth('t.transaction_date', (int) $this->reportMonth))
            ->when($this->mode === 'periode' && $this->periodStart && $this->periodEnd, fn ($q) => $q
                ->whereDate('t.transaction_date', '>=', $this->periodStart)
                ->whereDate('t.transaction_date', '<=', $this->periodEnd));
    }

    protected function getQuery()
    {
        $query = Transaction::query()
            ->from('transactions as t')
            ->join('coas as c', 'c.id', '=', 't.coa_id')
            ->whereIn('c.category', ['pemasukan', 'pengeluaran'])
            ->select([
                'c.code as kode',
                'c.name as nama',
                'c.category as kategori',
                DB::raw('SUM(t.amount) as jumlah'),
            ])
            ->groupBy('c.id', 'c.code', 'c.name', 'c.category')
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
            TextColumn::make('kategori')
                ->label('Jenis')
                ->badge()
                ->formatStateUsing(fn (string $state): string => $state === 'pemasukan' ? 'Pendapatan' : 'Beban')
                ->color(fn (string $state): string => $state === 'pemasukan' ? 'success' : 'danger'),
            TextColumn::make('jumlah')
                ->label('Jumlah')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($record): string => $record->kategori === 'pemasukan' ? 'success' : 'danger')
                ->sortable(),
        ];
    }
}
