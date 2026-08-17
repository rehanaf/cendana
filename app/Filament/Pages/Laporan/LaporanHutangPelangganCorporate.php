<?php

namespace App\Filament\Pages\Laporan;

use App\Models\PelangganCorporate;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;

class LaporanHutangPelangganCorporate extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Hutang Pelanggan Corporate';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-hutang-pelanggan-corporate';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-users';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $hutangPenjualan = $rows->sum(fn ($r) => (float) $r->total_tagihan - (float) $r->total_bayar);
        $hutangLangganan = $rows->sum(fn ($r) => (float) $r->total_langganan - (float) $r->total_bayar_langganan);

        return Grid::make(4)
            ->schema([
                $this->statCount('Jumlah Pelanggan', $rows->count()),
                $this->stat('Total Hutang', $hutangPenjualan + $hutangLangganan, ($hutangPenjualan + $hutangLangganan) > 0 ? 'danger' : 'success'),
                $this->stat('Hutang Penjualan', $hutangPenjualan, $hutangPenjualan > 0 ? 'danger' : 'success'),
                $this->stat('Hutang Langganan', $hutangLangganan, $hutangLangganan > 0 ? 'danger' : 'success'),
            ]);
    }

    protected function getQuery()
    {
        return PelangganCorporate::query()
            ->select('corporate_customers.*')
            ->selectRaw('COALESCE((SELECT SUM(s.total) FROM sales s WHERE s.customer_id = corporate_customers.id), 0) as total_tagihan')
            ->selectRaw('COALESCE((SELECT SUM(t.amount) FROM transactions t JOIN sales s2 ON t.sale_id = s2.id WHERE s2.customer_id = corporate_customers.id), 0) as total_bayar')
            ->selectRaw('COALESCE((SELECT SUM(si.total) FROM subscription_invoices si WHERE si.customer_id = corporate_customers.id), 0) as total_langganan')
            ->selectRaw('COALESCE((SELECT SUM(t3.amount) FROM transactions t3 JOIN subscription_invoices si2 ON t3.subscription_invoice_id = si2.id WHERE si2.customer_id = corporate_customers.id), 0) as total_bayar_langganan')
            ->whereRaw("(
                COALESCE((SELECT SUM(s.total) FROM sales s WHERE s.customer_id = corporate_customers.id), 0)
                - COALESCE((SELECT SUM(t.amount) FROM transactions t JOIN sales s2 ON t.sale_id = s2.id WHERE s2.customer_id = corporate_customers.id), 0)
                + COALESCE((SELECT SUM(si.total) FROM subscription_invoices si WHERE si.customer_id = corporate_customers.id), 0)
                - COALESCE((SELECT SUM(t3.amount) FROM transactions t3 JOIN subscription_invoices si2 ON t3.subscription_invoice_id = si2.id WHERE si2.customer_id = corporate_customers.id), 0)
            ) > 0");
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('customer_code')
                ->label('ID Pelanggan')
                ->searchable()
                ->sortable(),
            TextColumn::make('name')
                ->label('Nama Pelanggan')
                ->searchable()
                ->sortable(),
            TextColumn::make('total_tagihan')
                ->label('Tagihan Penjualan')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('total_langganan')
                ->label('Tagihan Langganan')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('dibayar')
                ->label('Total Dibayar')
                ->getStateUsing(fn ($record): float => (float) $record->total_bayar + (float) $record->total_bayar_langganan)
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success'),
            TextColumn::make('sisa')
                ->label('Total Hutang')
                ->getStateUsing(fn ($record): float => ((float) $record->total_tagihan - (float) $record->total_bayar) + ((float) $record->total_langganan - (float) $record->total_bayar_langganan))
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('danger')
                ->sortable(),
        ];
    }
}
