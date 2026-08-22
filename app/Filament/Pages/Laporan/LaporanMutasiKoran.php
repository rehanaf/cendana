<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use App\Models\Wallet;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanMutasiKoran extends BaseReportPage
{
    public ?string $walletId = '';

    public static function reportLabel(): string
    {
        return 'Mutasi Rekening Koran';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-mutasi-koran';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-list-bullet';
    }

    protected function reportFilterComponents(): array
    {
        return [
            Select::make('walletId')
                ->hiddenLabel()
                ->native(true)
                ->options(fn (): array =>
                    Wallet::where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->prepend('Semua Rekening', '')
                        ->toArray()
                )
                ->live()
                ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
            ...parent::reportFilterComponents(),
        ];
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $masuk = $rows->sum(fn ($r) => (float) $r->masuk);
        $keluar = $rows->sum(fn ($r) => (float) $r->keluar);

        return Grid::make(3)
            ->schema([
                $this->stat('Total Masuk', $masuk, 'success'),
                $this->stat('Total Keluar', $keluar, 'danger'),
                $this->statCount('Jumlah Transaksi', $rows->count()),
            ]);
    }

    protected function getQuery()
    {
        $query = Transaction::query()
            ->from('transactions as t')
            ->leftJoin('coas as c', 'c.id', '=', 't.coa_id')
            ->leftJoin('wallets as w', 'w.id', '=', 't.wallet_id')
            ->leftJoin('transaction_references as tr', 'tr.id', '=', 't.transaction_reference_id')
            ->select([
                't.transaction_date as date',
                DB::raw('COALESCE(NULLIF(t.description, \'\'), t.name) as keterangan'),
                DB::raw('COALESCE(tr.reference_no, \'\') as reference_no'),
                DB::raw('COALESCE(tr.description, \'\') as referensi_keterangan'),
                DB::raw('COALESCE(w.name, \'-\') as rekening'),
                DB::raw('COALESCE(c.name, \'-\') as akun'),
                DB::raw('CASE WHEN c.category = \'pemasukan\' THEN t.amount ELSE 0 END as masuk'),
                DB::raw('CASE WHEN c.category = \'pengeluaran\' THEN t.amount ELSE 0 END as keluar'),
                DB::raw('CASE WHEN c.category = \'transfer\' THEN t.amount ELSE 0 END as transfer'),
            ])
            ->orderBy('t.transaction_date', 'desc');

        $query = $this->applyModeFilter($query, 't.transaction_date');

        if ($this->walletId) {
            $query->where(function ($q) {
                $q->where('t.wallet_id', (int) $this->walletId)
                    ->orWhere('t.to_wallet_id', (int) $this->walletId);
            });
        }

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
            TextColumn::make('reference_no')
                ->label('Referensi')
                ->searchable()
                ->copyable()
                ->badge()
                ->color('gray')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('referensi_keterangan')
                ->label('Ket. Referensi')
                ->limit(40)
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('rekening')
                ->label('Rekening')
                ->searchable(),
            TextColumn::make('akun')
                ->label('Akun')
                ->searchable(),
            TextColumn::make('masuk')
                ->label('Masuk')
                ->formatStateUsing(fn ($state): string => (float) $state > 0 ? $this->money((float) $state) : '-')
                ->color('success')
                ->sortable(),
            TextColumn::make('keluar')
                ->label('Keluar')
                ->formatStateUsing(fn ($state): string => (float) $state > 0 ? $this->money((float) $state) : '-')
                ->color('danger')
                ->sortable(),
            TextColumn::make('transfer')
                ->label('Transfer')
                ->formatStateUsing(fn ($state): string => (float) $state > 0 ? $this->money((float) $state) : '-')
                ->color('warning')
                ->toggleable(),
        ];
    }
}
