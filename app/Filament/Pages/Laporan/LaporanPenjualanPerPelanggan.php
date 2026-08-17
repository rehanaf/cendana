<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanPenjualanPerPelanggan extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Penjualan Per Pelanggan';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-penjualan-per-pelanggan';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-user-group';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->total);
        $paid = $rows->sum(fn ($r) => (float) $r->dibayar);
        $sisa = $rows->sum(fn ($r) => (float) $r->sisa);

        return Grid::make(4)
            ->schema([
                $this->stat('Total Penjualan', $total),
                $this->stat('Total Dibayar', $paid, 'success'),
                $this->stat('Belum Dibayar', $sisa, $sisa > 0 ? 'danger' : 'success'),
                $this->statCount('Jumlah Pelanggan', $rows->count()),
            ]);
    }

    protected function salesSub()
    {
        return DB::table('sales as s')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 's.customer_id')
            ->select([
                DB::raw('COALESCE(c.name, \'-\') as nama'),
                's.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sale_id = s.id) as paid'),
                's.date',
            ]);
    }

    protected function subscriptionSub()
    {
        return DB::table('subscription_invoices as si')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 'si.customer_id')
            ->select([
                DB::raw('COALESCE(c.name, \'-\') as nama'),
                'si.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = si.id) as paid'),
                'si.date',
            ]);
    }

    protected function retailSub()
    {
        return DB::table('retail_invoices as ri')
            ->leftJoin('retail_customers as rc', 'rc.id', '=', 'ri.retail_customer_id')
            ->select([
                DB::raw('COALESCE(rc.name, \'-\') as nama'),
                'ri.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.retail_invoice_id = ri.id) as paid'),
                'ri.date',
            ]);
    }

    protected function getQuery()
    {
        $union = $this->salesSub()->unionAll($this->subscriptionSub())->unionAll($this->retailSub());

        $query = Transaction::query()
            ->fromSub($union, 'penjualan')
            ->select([
                'nama',
                DB::raw('COUNT(*) as jumlah_nota'),
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(paid) as dibayar'),
                DB::raw('SUM(total) - SUM(paid) as sisa'),
            ])
            ->groupBy('nama');

        $query = $this->applyModeFilter($query, 'date');

        return $query;
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('nama')
                ->label('Pelanggan')
                ->searchable()
                ->sortable(),
            TextColumn::make('jumlah_nota')
                ->label('Jumlah Nota')
                ->alignRight()
                ->sortable(),
            TextColumn::make('total')
                ->label('Total')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('dibayar')
                ->label('Dibayar')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success'),
            TextColumn::make('sisa')
                ->label('Sisa')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
        ];
    }
}
