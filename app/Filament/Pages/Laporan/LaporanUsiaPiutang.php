<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanUsiaPiutang extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Usia Piutang';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-usia-piutang';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-clock';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $totalSisa = $rows->sum(fn ($r) => (float) $r->sisa_bayar);
        $lancar = $rows->where('bucket', 'lancar')->sum(fn ($r) => (float) $r->sisa_bayar);
        $terlambat = $totalSisa - $lancar;

        return Grid::make(4)
            ->schema([
                $this->stat('Total Piutang', $totalSisa, $totalSisa > 0 ? 'danger' : 'success'),
                $this->stat('Belum Jatuh Tempo', $lancar, 'success'),
                $this->stat('Sudah Jatuh Tempo', $terlambat, $terlambat > 0 ? 'danger' : 'success'),
                $this->statCount('Jumlah Tagihan', $rows->count()),
            ]);
    }

    protected function salesSub()
    {
        return DB::table('sales as s')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 's.customer_id')
            ->select([
                's.invoice_no as invoice_no',
                DB::raw('COALESCE(c.name, \'-\') as customer_name'),
                's.date as date',
                's.due_date as due_date',
                's.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sale_id = s.id) as paid'),
            ])
            ->whereRaw('s.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.sale_id = s.id), 0)');
    }

    protected function subscriptionSub()
    {
        return DB::table('subscription_invoices as si')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 'si.customer_id')
            ->select([
                'si.invoice_no as invoice_no',
                DB::raw('COALESCE(c.name, \'-\') as customer_name'),
                'si.date as date',
                'si.due_date as due_date',
                'si.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = si.id) as paid'),
            ])
            ->whereRaw('si.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.subscription_invoice_id = si.id), 0)');
    }

    protected function retailSub()
    {
        return DB::table('retail_invoices as ri')
            ->leftJoin('retail_customers as rc', 'rc.id', '=', 'ri.retail_customer_id')
            ->select([
                'ri.invoice_no as invoice_no',
                DB::raw('COALESCE(rc.name, \'-\') as customer_name'),
                'ri.date as date',
                'ri.due_date as due_date',
                'ri.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.retail_invoice_id = ri.id) as paid'),
            ])
            ->whereRaw('ri.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.retail_invoice_id = ri.id), 0)');
    }

    protected function getQuery()
    {
        $union = $this->salesSub()->unionAll($this->subscriptionSub())->unionAll($this->retailSub());

        $query = Transaction::query()
            ->fromSub($union, 'piutang')
            ->select([
                'invoice_no',
                'customer_name',
                'date',
                'due_date',
                'total',
                'paid',
                DB::raw('total - paid as sisa_bayar'),
                DB::raw('CASE WHEN due_date >= CURRENT_DATE THEN \'lancar\' ELSE \'terlambat\' END as bucket'),
            ])
            ->orderBy('due_date');

        return $query;
    }

    protected function getColumns(): array
    {
        return [
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
            TextColumn::make('due_date')
                ->label('Jatuh Tempo')
                ->date('d F Y')
                ->sortable(),
            TextColumn::make('sisa_bayar')
                ->label('Sisa')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('danger')
                ->sortable(),
            TextColumn::make('umur')
                ->label('Umur (Hari)')
                ->getStateUsing(fn ($record): int => (int) \Carbon\Carbon::parse($record->due_date)->diffInDays(now(), false))
                ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'success'),
            TextColumn::make('bucket')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (string $state): string => $state === 'lancar' ? 'Belum Jatuh Tempo' : 'Jatuh Tempo')
                ->color(fn (string $state): string => $state === 'lancar' ? 'success' : 'danger'),
        ];
    }
}
