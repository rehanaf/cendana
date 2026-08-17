<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Coa;
use App\Models\Transaction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LaporanBukuBesar extends BaseReportPage
{
    public ?string $coaId = '';

    public static function reportLabel(): string
    {
        return 'Buku Besar';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-buku-besar';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-book-open';
    }

    protected function reportFilterComponents(): array
    {
        return [
            ...parent::reportFilterComponents(),
            Select::make('coaId')
                ->hiddenLabel()
                ->native(true)
                ->options(fn (): array =>
                    Coa::orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code . ' - ' . $coa->name])
                        ->prepend('Semua Akun', '')
                        ->toArray()
                )
                ->live()
                ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
        ];
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $debit = $rows->sum(fn ($r) => (float) $r->debit);
        $kredit = $rows->sum(fn ($r) => (float) $r->kredit);

        return Grid::make(4)
            ->schema([
                $this->stat('Total Debit', $debit, 'danger'),
                $this->stat('Total Kredit', $kredit, 'success'),
                $this->stat('Saldo', $kredit - $debit),
                $this->statCount('Jumlah Akun', $rows->count()),
            ]);
    }

    protected function getQuery(): Builder
    {
        $query = Transaction::query()
            ->from('transactions as t')
            ->join('coas as c', 'c.id', '=', 't.coa_id')
            ->select([
                'c.code as kode',
                'c.name as nama',
                DB::raw('COALESCE(SUM(CASE WHEN c.category = \'pengeluaran\' THEN t.amount ELSE 0 END), 0) as debit'),
                DB::raw('COALESCE(SUM(CASE WHEN c.category = \'pemasukan\' THEN t.amount ELSE 0 END), 0) as kredit'),
                DB::raw('COUNT(t.id) as jumlah_transaksi'),
            ])
            ->groupBy('c.id', 'c.code', 'c.name')
            ->orderBy('c.code');

        $query = $this->applyModeFilter($query, 't.transaction_date');

        if ($this->coaId) {
            $query->where('t.coa_id', (int) $this->coaId);
        }

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
                ->label('Nama Akun')
                ->searchable()
                ->sortable(),
            TextColumn::make('debit')
                ->label('Debit')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('danger')
                ->sortable(),
            TextColumn::make('kredit')
                ->label('Kredit')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success')
                ->sortable(),
            TextColumn::make('saldo')
                ->label('Saldo')
                ->getStateUsing(fn ($record): float => (float) $record->kredit - (float) $record->debit)
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($state): string => (float) $state >= 0 ? 'success' : 'danger'),
            TextColumn::make('jumlah_transaksi')
                ->label('Transaksi')
                ->alignRight(),
        ];
    }
}
