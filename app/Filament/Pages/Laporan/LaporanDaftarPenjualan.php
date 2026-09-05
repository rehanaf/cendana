<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Coa;
use App\Models\Transaction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanDaftarPenjualan extends BaseReportPage
{
    public ?string $coaId = '';

    public ?string $mode = 'semua';

    public static function reportLabel(): string
    {
        return 'Daftar Penjualan';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-daftar-penjualan';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-receipt-refund';
    }

    protected function reportFilterComponents(): array
    {
        return [
            Select::make('coaId')
                ->hiddenLabel()
                ->native(true)
                ->options(fn (): array =>
                    Coa::query()
                        ->where('type', 'income')
                        ->where('is_active', true)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Coa $c): array => [$c->id => $c->code . ' - ' . $c->name])
                        ->prepend('Semua Akun Penjualan', '')
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

        $total = $rows->sum(fn ($r) => (float) $r->total);
        $paid = $rows->sum(fn ($r) => (float) $r->total_paid);
        $sisa = $rows->sum(fn ($r) => max(0, (float) $r->total - (float) $r->total_paid));

        return Grid::make(4)
            ->schema([
                $this->stat('Total Penjualan', $total),
                $this->stat('Total Dibayar', $paid, 'success'),
                $this->stat('Belum Dibayar', $sisa, $sisa > 0 ? 'danger' : 'success'),
                $this->statCount('Jumlah Nota', $rows->count()),
            ]);
    }

    protected function salesSub()
    {
        return DB::table('sales as s')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('coas as co', 'co.id', '=', 's.coa_id')
            ->where('co.is_active', true)
            ->select([
                's.invoice_no as invoice_no',
                's.date as date',
                DB::raw('COALESCE(c.name, \'\') as customer_name'),
                DB::raw('\'Corporate\' as customer_type'),
                DB::raw('\'Penjualan\' as sumber_label'),
                's.coa_id as coa_id',
                DB::raw('COALESCE(co.name, \'-\') as coa_name'),
                's.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sale_id = s.id) as total_paid'),
                's.status as status',
            ]);
    }

    protected function subscriptionSub()
    {
        return DB::table('subscription_invoices as si')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 'si.customer_id')
            ->leftJoin('coas as co', 'co.id', '=', 'si.coa_id')
            ->where('co.is_active', true)
            ->select([
                'si.invoice_no as invoice_no',
                'si.date as date',
                DB::raw('COALESCE(c.name, \'\') as customer_name'),
                DB::raw('\'Corporate\' as customer_type'),
                DB::raw('\'Langganan\' as sumber_label'),
                'si.coa_id as coa_id',
                DB::raw('COALESCE(co.name, \'-\') as coa_name'),
                'si.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = si.id) as total_paid'),
                'si.status as status',
            ]);
    }

    protected function retailSub()
    {
        return DB::table('retail_invoices as ri')
            ->leftJoin('retail_customers as rc', 'rc.id', '=', 'ri.retail_customer_id')
            ->leftJoin('coas as co', 'co.id', '=', 'ri.coa_id')
            ->where('co.is_active', true)
            ->select([
                'ri.invoice_no as invoice_no',
                'ri.date as date',
                DB::raw('COALESCE(rc.name, \'\') as customer_name'),
                DB::raw('\'Retail\' as customer_type'),
                DB::raw('\'Retail\' as sumber_label'),
                'ri.coa_id as coa_id',
                DB::raw('COALESCE(co.name, \'-\') as coa_name'),
                'ri.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.retail_invoice_id = ri.id) as total_paid'),
                'ri.status as status',
            ]);
    }

    protected function otherIncomeSub()
    {
        return DB::table('transactions as t')
            ->join('coas as co', 'co.id', '=', 't.coa_id')
            ->where('co.type', 'income')
            ->where('co.is_active', true)
            ->whereNull('t.sale_id')
            ->whereNull('t.purchase_id')
            ->whereNull('t.subscription_invoice_id')
            ->whereNull('t.retail_invoice_id')
            ->select([
                DB::raw('COALESCE(NULLIF(t.description, \'\'), CONCAT(\'Transaksi #\', t.id)) as invoice_no'),
                't.transaction_date as date',
                DB::raw('\'\' as customer_name'),
                DB::raw('\'-\' as customer_type'),
                DB::raw('\'Pendapatan Lain\' as sumber_label'),
                't.coa_id as coa_id',
                DB::raw('COALESCE(co.name, \'-\') as coa_name'),
                't.amount as total',
                't.amount as total_paid',
                DB::raw('\'lunas\' as status'),
            ]);
    }

    protected function getQuery()
    {
        $parts = [
            $this->salesSub(),
            $this->subscriptionSub(),
            $this->retailSub(),
            $this->otherIncomeSub(),
        ];

        $union = array_shift($parts);

        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        $query = Transaction::query()
            ->fromSub($union, 'penjualan')
            ->select(['invoice_no', 'date', 'customer_name', 'customer_type', 'sumber_label', 'coa_id', 'coa_name', 'total', 'total_paid', 'status']);

        if ($this->coaId) {
            $query->where('coa_id', (int) $this->coaId);
        }

        return $this->applyModeFilter($query, 'date');
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('invoice_no')
                ->label('No. Nota')
                ->searchable()
                ->sortable(),
            TextColumn::make('date')
                ->label('Tanggal')
                ->date('d F Y')
                ->sortable(),
            TextColumn::make('customer_name')
                ->label('Pelanggan')
                ->searchable()
                ->sortable(),
            TextColumn::make('customer_type')
                ->label('Tipe Pelanggan')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Corporate' => 'info',
                    'Retail' => 'success',
                    default => 'gray',
                }),
            TextColumn::make('coa_name')
                ->label('Akun COA')
                ->badge()
                ->color('primary')
                ->searchable()
                ->sortable(),
            TextColumn::make('sumber_label')
                ->label('Sumber')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Penjualan' => 'info',
                    'Langganan' => 'warning',
                    'Retail' => 'success',
                    default => 'gray',
                }),
            TextColumn::make('total')
                ->label('Total')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('total_paid')
                ->label('Dibayar')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success'),
            TextColumn::make('sisa')
                ->label('Sisa')
                ->getStateUsing(fn ($record): float => max(0, (float) $record->total - (float) $record->total_paid))
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'lunas' => 'Lunas',
                    'berjalan' => 'Belum Lunas',
                    'tak_tertagih' => 'Tak Tertagih',
                    default => $state,
                })
                ->color(fn (string $state): string => match ($state) {
                    'lunas' => 'success',
                    'berjalan' => 'warning',
                    'tak_tertagih' => 'danger',
                    default => 'gray',
                }),
        ];
    }
}
