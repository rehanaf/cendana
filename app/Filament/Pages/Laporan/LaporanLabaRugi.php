<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanLabaRugi extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Laba Rugi';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cetak')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->url(fn (): string => route('laporan.laba-rugi.cetak', [
                    'month' => $this->reportMonth ?: now()->format('m'),
                    'year' => $this->reportYear ?: now()->format('Y'),
                    'print' => 1,
                ]))
                ->openUrlInNewTab(),
            Action::make('preview')
                ->label('Preview Contoh')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => route('laporan.laba-rugi.cetak', [
                    'month' => $this->reportMonth ?: now()->format('m'),
                    'year' => $this->reportYear ?: now()->format('Y'),
                    'preview' => 1,
                ]))
                ->openUrlInNewTab(),
        ];
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
        $cogs = (float) $this->cogsQuery()->sum('t.amount');
        $expense = (float) $this->expenseQuery()->sum('t.amount');
        $laba = $income - $cogs - $expense;

        return Grid::make(4)
            ->schema([
                $this->stat('Pendapatan', $income, 'success'),
                $this->stat('HPP / Pembelian', $cogs, 'warning'),
                $this->stat('Beban & Pajak', $expense, 'danger'),
                $this->stat('Laba / Rugi', $laba, $laba >= 0 ? 'success' : 'danger'),
            ]);
    }

    protected function incomeQuery()
    {
        return Transaction::query()
            ->from('transactions as t')
            ->join('coas as c', 'c.id', '=', 't.coa_id')
            ->where('c.type', 'income')
            ->when($this->mode === 'harian' && $this->date, fn ($q) => $q->whereDate('t.transaction_date', $this->date))
            ->when($this->mode === 'bulanan', fn ($q) => $q
                ->whereYear('t.transaction_date', (int) $this->reportYear)
                ->whereMonth('t.transaction_date', (int) $this->reportMonth))
            ->when($this->mode === 'periode' && $this->periodStart && $this->periodEnd, fn ($q) => $q
                ->whereDate('t.transaction_date', '>=', $this->periodStart)
                ->whereDate('t.transaction_date', '<=', $this->periodEnd));
    }

    protected function cogsQuery()
    {
        return Transaction::query()
            ->from('transactions as t')
            ->join('coas as c', 'c.id', '=', 't.coa_id')
            ->where('c.type', 'cogs')
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
            ->whereIn('c.type', ['expense', 'tax'])
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
            ->whereIn('c.type', ['income', 'cogs', 'expense', 'tax'])
            ->select([
                'c.code as kode',
                'c.name as nama',
                'c.type as tipe',
                DB::raw('SUM(t.amount) as jumlah'),
            ])
            ->groupBy('c.id', 'c.code', 'c.name', 'c.type')
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
            TextColumn::make('tipe')
                ->label('Kelompok')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'income' => 'Pendapatan',
                    'cogs' => 'HPP / Pembelian',
                    'expense' => 'Beban',
                    'tax' => 'Pajak',
                    default => $state,
                })
                ->color(fn (string $state): string => match ($state) {
                    'income' => 'success',
                    'cogs' => 'warning',
                    'expense' => 'danger',
                    'tax' => 'danger',
                    default => 'gray',
                }),
            TextColumn::make('jumlah')
                ->label('Jumlah')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($record): string => $record->tipe === 'income' ? 'success' : 'danger')
                ->sortable(),
        ];
    }
}
