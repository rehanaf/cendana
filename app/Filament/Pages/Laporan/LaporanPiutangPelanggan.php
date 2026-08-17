<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanPiutangPelanggan extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Piutang Pelanggan';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-piutang-pelanggan';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-arrow-trending-up';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $totalSisa = $rows->sum(fn ($r) => (float) $r->sisa_bayar);
        $sisaPenjualan = $rows->where('source', 'Penjualan')->sum(fn ($r) => (float) $r->sisa_bayar);
        $sisaLangganan = $rows->where('source', 'Langganan')->sum(fn ($r) => (float) $r->sisa_bayar);

        return Grid::make(4)
            ->schema([
                $this->stat('Total Piutang', $totalSisa, $totalSisa > 0 ? 'danger' : 'success'),
                $this->stat('Piutang Penjualan', $sisaPenjualan, $sisaPenjualan > 0 ? 'danger' : 'success'),
                $this->stat('Piutang Langganan', $sisaLangganan, $sisaLangganan > 0 ? 'danger' : 'success'),
                $this->statCount('Jumlah Tagihan', $rows->count()),
            ]);
    }

    protected function salesSub()
    {
        return DB::table('sales as s')
            ->join('corporate_customers as c', 'c.id', '=', 's.customer_id')
            ->select([
                's.invoice_no as invoice_no',
                DB::raw('\'Penjualan\' as source'),
                'c.name as customer_name',
                's.date as date',
                's.total as total',
                DB::raw('COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.sale_id = s.id), 0) as paid'),
            ])
            ->whereRaw('s.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.sale_id = s.id), 0)');
    }

    protected function langgananSub()
    {
        return DB::table('subscription_invoices as si')
            ->join('corporate_customers as c', 'c.id', '=', 'si.customer_id')
            ->select([
                'si.invoice_no as invoice_no',
                DB::raw('\'Langganan\' as source'),
                'c.name as customer_name',
                'si.date as date',
                'si.total as total',
                DB::raw('COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.subscription_invoice_id = si.id), 0) as paid'),
            ])
            ->whereRaw('si.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.subscription_invoice_id = si.id), 0)');
    }

    protected function getQuery()
    {
        $union = $this->salesSub()->union($this->langgananSub());

        $query = Transaction::query()
            ->fromSub($union, 'piutang')
            ->select([
                'invoice_no',
                'source',
                'customer_name',
                'date',
                'total',
                'paid',
                DB::raw('total - paid as sisa_bayar'),
            ]);

        return $this->applyModeFilter($query, 'date');
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('source')
                ->label('Sumber')
                ->badge()
                ->formatStateUsing(fn (string $state): string => $state === 'Penjualan' ? 'Penjualan' : 'Langganan')
                ->color(fn (string $state): string => $state === 'Penjualan' ? 'info' : 'warning'),
            TextColumn::make('invoice_no')
                ->label('No. Invoice')
                ->searchable()
                ->sortable(),
            TextColumn::make('customer_name')
                ->label('Pelanggan')
                ->searchable()
                ->sortable(),
            TextColumn::make('date')
                ->label('Tanggal')
                ->date('d F Y')
                ->sortable(),
            TextColumn::make('total')
                ->label('Total')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('paid')
                ->label('Dibayar')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success'),
            TextColumn::make('sisa_bayar')
                ->label('Sisa')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('danger')
                ->sortable(),
        ];
    }
}
