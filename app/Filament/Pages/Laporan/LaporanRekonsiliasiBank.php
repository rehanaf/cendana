<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Wallet;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanRekonsiliasiBank extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Ringkasan Rekonsiliasi Bank';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-rekonsiliasi-bank';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-banknotes';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $saldo = $rows->sum(fn ($r) => (float) $r->saldo_akhir);
        $selisih = $rows->sum(fn ($r) => (float) $r->saldo_akhir - (float) $r->saldo_sistem);

        return Grid::make(3)
            ->schema([
                $this->stat('Total Saldo Rekening', $saldo),
                $this->stat('Total Selisih', $selisih, abs($selisih) > 0 ? 'warning' : 'success'),
                $this->statCount('Jumlah Rekening', $rows->count()),
            ]);
    }

    protected function periodStartDate(): string
    {
        return match ($this->mode) {
            'harian' => $this->date ?? now()->format('Y-m-d'),
            'bulanan' => now()->create($this->reportYear ?: now()->year, $this->reportMonth ?: now()->month, 1)
                ->startOfMonth()->format('Y-m-d'),
            default => $this->periodStart ?? now()->startOfMonth()->format('Y-m-d'),
        };
    }

    protected function periodEndDate(): string
    {
        return match ($this->mode) {
            'harian' => $this->date ?? now()->format('Y-m-d'),
            'bulanan' => now()->create($this->reportYear ?: now()->year, $this->reportMonth ?: now()->month, 1)
                ->endOfMonth()->format('Y-m-d'),
            default => $this->periodEnd ?? now()->format('Y-m-d'),
        };
    }

    protected function getQuery()
    {
        $start = $this->periodStartDate();
        $end = $this->periodEndDate();

        return Wallet::query()
            ->from('wallets as w')
            ->where('w.is_active', true)
            ->select([
                'w.name as nama',
                DB::raw('COALESCE((
                    SELECT SUM(CASE WHEN c.category = \'pemasukan\' THEN t.amount ELSE -t.amount END)
                    FROM transactions t JOIN coas c ON c.id = t.coa_id
                    WHERE t.wallet_id = w.id AND t.transaction_date < ?
                ), 0) + COALESCE((
                    SELECT SUM(amount) FROM transactions WHERE to_wallet_id = w.id AND transaction_date < ?
                ), 0) as saldo_awal', [$start, $start]),
                DB::raw('COALESCE((
                    SELECT SUM(CASE WHEN c.category = \'pemasukan\' THEN t.amount ELSE -t.amount END)
                    FROM transactions t JOIN coas c ON c.id = t.coa_id
                    WHERE t.wallet_id = w.id AND t.transaction_date <= ?
                ), 0) + COALESCE((
                    SELECT SUM(amount) FROM transactions WHERE to_wallet_id = w.id AND transaction_date <= ?
                ), 0) as saldo_akhir', [$end, $end]),
                DB::raw('COALESCE((
                    SELECT SUM(CASE WHEN c.category = \'pemasukan\' THEN t.amount ELSE -t.amount END)
                    FROM transactions t JOIN coas c ON c.id = t.coa_id
                    WHERE t.wallet_id = w.id AND t.transaction_date >= ? AND t.transaction_date <= ?
                ), 0) + COALESCE((
                    SELECT SUM(amount) FROM transactions WHERE to_wallet_id = w.id AND transaction_date >= ? AND transaction_date <= ?
                ), 0) as mutasi', [$start, $end, $start, $end]),
                'w.balance as saldo_sistem',
            ])
            ->orderBy('w.name');
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('nama')
                ->label('Rekening / Dompet')
                ->searchable()
                ->sortable(),
            TextColumn::make('saldo_awal')
                ->label('Saldo Awal')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state)),
            TextColumn::make('mutasi')
                ->label('Mutasi')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($state): string => (float) $state >= 0 ? 'success' : 'danger'),
            TextColumn::make('saldo_akhir')
                ->label('Saldo Akhir')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('saldo_sistem')
                ->label('Saldo Sistem')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state)),
            TextColumn::make('selisih')
                ->label('Selisih')
                ->getStateUsing(fn ($record): float => (float) $record->saldo_akhir - (float) $record->saldo_sistem)
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($state): string => abs((float) $state) > 0 ? 'warning' : 'success'),
        ];
    }
}
