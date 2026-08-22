<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Purchase;
use App\Models\Wallet;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanDaftarPembelian extends BaseReportPage
{
    public ?string $mode = 'semua';

    public ?string $walletId = '';

    public static function reportLabel(): string
    {
        return 'Daftar Pembelian';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-daftar-pembelian';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-receipt-refund';
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
                        ->prepend('Semua Dompet', '')
                        ->toArray()
                )
                ->live()
                ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
            ...parent::reportFilterComponents(),
        ];
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery(applyWallet: false)->get();

        $total = $rows->sum(fn ($r) => (float) $r->total);
        $paid = $rows->sum(fn ($r) => (float) $r->paid);
        $sisa = $rows->sum(fn ($r) => (float) $r->sisa);

        return Grid::make(4)
            ->schema([
                $this->stat('Total Pembelian', $total),
                $this->stat('Total Dibayar', $paid, 'success'),
                $this->stat('Belum Dibayar', $sisa, $sisa > 0 ? 'danger' : 'success'),
                $this->statCount('Jumlah Nota', $rows->count()),
            ]);
    }

    protected function getQuery(bool $applyWallet = true)
    {
        $query = Purchase::query()
            ->leftJoin('vendors as v', 'v.id', '=', 'purchases.vendor_id')
            ->select([
                'purchases.invoice_no as invoice_no',
                'purchases.date as date',
                'purchases.due_date as due_date',
                DB::raw('COALESCE(v.name, \'\') as vendor_name'),
                'purchases.notes as notes',
                'purchases.total as total',
                DB::raw('COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = purchases.id), 0) as paid'),
                DB::raw('purchases.total - COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = purchases.id), 0) as sisa'),
                'purchases.status as status',
            ])
            ->orderBy('purchases.date', 'desc');

        $query = $this->applyModeFilter($query, 'purchases.date');

        if ($applyWallet && $this->walletId) {
            $query->where('purchases.wallet_id', (int) $this->walletId);
        }

        return $query;
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('invoice_no')
                ->label('No. Nota')
                ->searchable()
                ->sortable()
                ->toggleable(),
            TextColumn::make('date')
                ->label('Tanggal')
                ->date('d F Y')
                ->sortable()
                ->toggleable(),
            TextColumn::make('due_date')
                ->label('Jatuh Tempo')
                ->date('d F Y')
                ->sortable()
                ->toggleable(),
            TextColumn::make('vendor_name')
                ->label('Vendor')
                ->searchable()
                ->sortable()
                ->toggleable(),
            TextColumn::make('notes')
                ->label('Keterangan')
                ->searchable()
                ->toggleable(),
            TextColumn::make('total')
                ->label('Total')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable()
                ->toggleable(),
            TextColumn::make('paid')
                ->label('Dibayar')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success')
                ->sortable()
                ->toggleable(),
            TextColumn::make('sisa')
                ->label('Sisa')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success')
                ->sortable()
                ->toggleable(),
            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'lunas' => 'Lunas',
                    'berjalan' => 'Belum Lunas',
                    default => $state,
                })
                ->color(fn (string $state): string => match ($state) {
                    'lunas' => 'success',
                    'berjalan' => 'warning',
                    default => 'gray',
                })
                ->toggleable(),
        ];
    }
}
