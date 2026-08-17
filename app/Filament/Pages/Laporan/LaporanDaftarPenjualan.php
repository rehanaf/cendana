<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanDaftarPenjualan extends BaseReportPage
{
    public ?string $source = '';

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
            Select::make('source')
                ->hiddenLabel()
                ->native(true)
                ->options([
                    '' => 'Semua Jenis',
                    'corporate' => 'Corporate',
                    'retail' => 'Retail',
                    'lainnya' => 'Pendapatan Lain',
                ])
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
            ->select([
                's.invoice_no as invoice_no',
                's.date as date',
                DB::raw('COALESCE(c.name, \'\') as customer_name'),
                DB::raw('\'Penjualan\' as sumber'),
                's.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sale_id = s.id) as total_paid'),
                's.status as status',
            ]);
    }

    protected function subscriptionSub()
    {
        return DB::table('subscription_invoices as si')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 'si.customer_id')
            ->select([
                'si.invoice_no as invoice_no',
                'si.date as date',
                DB::raw('COALESCE(c.name, \'\') as customer_name'),
                DB::raw('\'Langganan\' as sumber'),
                'si.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = si.id) as total_paid'),
                'si.status as status',
            ]);
    }

    protected function retailSub()
    {
        return DB::table('retail_invoices as ri')
            ->leftJoin('retail_customers as rc', 'rc.id', '=', 'ri.retail_customer_id')
            ->select([
                'ri.invoice_no as invoice_no',
                'ri.date as date',
                DB::raw('COALESCE(rc.name, \'\') as customer_name'),
                DB::raw('\'Retail\' as sumber'),
                'ri.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.retail_invoice_id = ri.id) as total_paid'),
                'ri.status as status',
            ]);
    }

    protected function otherIncomeSub()
    {
        return DB::table('transactions as t')
            ->join('coas as co', 'co.id', '=', 't.coa_id')
            ->where('co.category', 'pemasukan')
            ->whereNull('t.sale_id')
            ->whereNull('t.purchase_id')
            ->whereNull('t.subscription_invoice_id')
            ->whereNull('t.retail_invoice_id')
            ->select([
                DB::raw('COALESCE(NULLIF(t.description, \'\'), CONCAT(\'Transaksi #\', t.id)) as invoice_no'),
                't.transaction_date as date',
                DB::raw('\'\' as customer_name'),
                DB::raw('\'Pendapatan Lain\' as sumber'),
                't.amount as total',
                't.amount as total_paid',
                DB::raw('\'lunas\' as status'),
            ]);
    }

    protected function getQuery()
    {
        $parts = [];

        if ($this->source === '' || $this->source === 'corporate') {
            $parts[] = $this->salesSub();
            $parts[] = $this->subscriptionSub();
        }

        if ($this->source === '' || $this->source === 'retail') {
            $parts[] = $this->retailSub();
        }

        if ($this->source === '' || $this->source === 'lainnya') {
            $parts[] = $this->otherIncomeSub();
        }

        $union = array_shift($parts);

        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        $query = Transaction::query()
            ->fromSub($union, 'penjualan')
            ->select(['invoice_no', 'date', 'customer_name', 'sumber', 'total', 'total_paid', 'status']);

        $query = match ($this->source) {
            'corporate' => $query->whereIn('sumber', ['Penjualan', 'Langganan']),
            'retail' => $query->where('sumber', 'Retail'),
            'lainnya' => $query->where('sumber', 'Pendapatan Lain'),
            default => $query,
        };

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
            TextColumn::make('sumber')
                ->label('Jenis')
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
