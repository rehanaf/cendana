<?php

namespace App\Filament\Pages\Laporan;

use App\Models\SubscriptionInvoice;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanTagihanLangganan extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Laporan Langganan';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-tagihan-langganan';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-receipt-percent';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->total);
        $paid = $rows->sum(fn ($r) => (float) $r->paid);
        $sisa = $rows->sum(fn ($r) => (float) $r->sisa);

        return Grid::make(4)
            ->schema([
                $this->stat('Total Tagihan', $total),
                $this->stat('Total Dibayar', $paid, 'success'),
                $this->stat('Belum Dibayar', $sisa, $sisa > 0 ? 'danger' : 'success'),
                $this->statCount('Jumlah Tagihan', $rows->count()),
            ]);
    }

    protected function getQuery()
    {
        $query = SubscriptionInvoice::query()
            ->leftJoin('corporate_customers as c', 'c.id', '=', 'subscription_invoices.customer_id')
            ->select([
                'subscription_invoices.invoice_no as invoice_no',
                DB::raw('COALESCE(c.name, \'\') as customer_name'),
                'subscription_invoices.period as period',
                'subscription_invoices.due_date as due_date',
                'subscription_invoices.total as total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = subscription_invoices.id) as paid'),
                DB::raw('subscription_invoices.total - (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = subscription_invoices.id) as sisa'),
                'subscription_invoices.status as status',
            ])
            ->orderBy('subscription_invoices.period', 'desc');

        $query = $this->applyModeFilter($query, 'subscription_invoices.period');

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
            TextColumn::make('period')
                ->label('Periode')
                ->date('F Y')
                ->sortable(),
            TextColumn::make('due_date')
                ->label('Jatuh Tempo')
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
            TextColumn::make('sisa')
                ->label('Sisa')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
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
                }),
        ];
    }
}
