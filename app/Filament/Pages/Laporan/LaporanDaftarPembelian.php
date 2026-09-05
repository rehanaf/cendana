<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Coa;
use App\Models\Transaction;
use App\Models\Wallet;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanDaftarPembelian extends BaseReportPage
{
    public ?string $mode = 'semua';

    public ?string $walletId = '';

    public ?string $coaId = '';

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
            Select::make('coaId')
                ->hiddenLabel()
                ->native(true)
                ->options(fn (): array =>
                    Coa::query()
                        ->where('type', 'cogs')
                        ->where('is_active', true)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Coa $c): array => [$c->id => $c->code . ' - ' . $c->name])
                        ->prepend('Semua Akun Pembelian', '')
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

    protected function purchaseSub()
    {
        return DB::table('purchases as p')
            ->leftJoin('vendors as v', 'v.id', '=', 'p.vendor_id')
            ->leftJoin('coas as co', 'co.id', '=', 'p.coa_id')
            ->select([
                'p.invoice_no as invoice_no',
                'p.date as date',
                'p.due_date as due_date',
                DB::raw('COALESCE(v.name, \'\') as vendor_name'),
                'p.notes as notes',
                'p.coa_id as coa_id',
                DB::raw('COALESCE(co.name, \'-\') as coa_name'),
                DB::raw('\'Nota Pembelian\' as sumber_label'),
                'p.wallet_id as wallet_id',
                'p.total as total',
                DB::raw('COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = p.id), 0) as paid'),
                DB::raw('p.total - COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = p.id), 0) as sisa'),
                'p.status as status',
            ]);
    }

    protected function hppSub()
    {
        return DB::table('transactions as t')
            ->leftJoin('coas as co', 'co.id', '=', 't.coa_id')
            ->where('co.type', 'cogs')
            ->where('co.is_active', true)
            ->whereNull('t.purchase_id')
            ->whereNull('t.sale_id')
            ->whereNull('t.subscription_invoice_id')
            ->whereNull('t.retail_invoice_id')
            ->select([
                DB::raw('COALESCE(NULLIF(t.description, \'\'), CONCAT(\'Pengeluaran #\', t.id)) as invoice_no'),
                't.transaction_date as date',
                DB::raw('NULL as due_date'),
                DB::raw('\'\' as vendor_name'),
                DB::raw('\'\' as notes'),
                't.coa_id as coa_id',
                DB::raw('COALESCE(co.name, \'-\') as coa_name'),
                DB::raw('\'Pengeluaran HPP\' as sumber_label'),
                't.wallet_id as wallet_id',
                't.amount as total',
                't.amount as paid',
                DB::raw('0 as sisa'),
                DB::raw('\'lunas\' as status'),
            ]);
    }

    protected function getQuery(bool $applyWallet = true)
    {
        $union = $this->purchaseSub()->unionAll($this->hppSub());

        $query = Transaction::query()
            ->fromSub($union, 'pembelian')
            ->select(['invoice_no', 'date', 'due_date', 'vendor_name', 'notes', 'coa_id', 'coa_name', 'sumber_label', 'wallet_id', 'total', 'paid', 'sisa', 'status']);

        if ($this->coaId) {
            $query->where('coa_id', (int) $this->coaId);
        }

        if ($applyWallet && $this->walletId) {
            $query->where('wallet_id', (int) $this->walletId);
        }

        return $this->applyModeFilter($query, 'date');
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
            TextColumn::make('coa_name')
                ->label('Akun COA')
                ->badge()
                ->color('primary')
                ->searchable()
                ->sortable()
                ->toggleable(),
            TextColumn::make('sumber_label')
                ->label('Sumber')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Nota Pembelian' => 'info',
                    'Pengeluaran HPP' => 'warning',
                    default => 'gray',
                })
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
