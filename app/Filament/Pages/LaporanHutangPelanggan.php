<?php

namespace App\Filament\Pages;

use App\Models\PelangganCorporate;
use App\Models\Sale;
use App\Models\SubscriptionInvoice;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LaporanHutangPelanggan extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static ?int $navigationSort = 7;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationLabel(): string
    {
        return 'Hutang Pelanggan Corporate';
    }

    public function getTitle(): string
    {
        return 'Hutang Pelanggan Corporate';
    }

    public static function getDefaultSlug(): string
    {
        return 'hutang-pelanggan-corporate';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hutang Pelanggan Corporate')
                    ->description('Total saldo tagihan (piutang) per pelanggan corporate yang belum lunas')
                    ->schema([
                        $this->getStatsGrid(),
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    public function getStatsGrid(): Grid
    {
        $records = $this->getQuery()->get();

        $hutangPenjualan = $records->sum(function (PelangganCorporate $record): float {
            return (float) $record->total_tagihan - (float) $record->total_bayar;
        });
        $hutangLangganan = $records->sum(function (PelangganCorporate $record): float {
            return (float) $record->total_langganan - (float) $record->total_bayar_langganan;
        });

        return Grid::make(4)
            ->schema([
                Stat::make('Jumlah Pelanggan', (string) $records->count()),
                Stat::make('Total Hutang', 'Rp ' . number_format($hutangPenjualan + $hutangLangganan, 0, ',', '.'))
                    ->color(($hutangPenjualan + $hutangLangganan) > 0 ? 'danger' : 'success'),
                Stat::make('Hutang Penjualan', 'Rp ' . number_format($hutangPenjualan, 0, ',', '.'))
                    ->color($hutangPenjualan > 0 ? 'danger' : 'success'),
                Stat::make('Hutang Langganan', 'Rp ' . number_format($hutangLangganan, 0, ',', '.'))
                    ->color($hutangLangganan > 0 ? 'danger' : 'success'),
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

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn () => $this->getQuery())
            ->columns([
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
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                TextColumn::make('total_langganan')
                    ->label('Tagihan Langganan')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                TextColumn::make('dibayar')
                    ->label('Total Dibayar')
                    ->getStateUsing(fn (PelangganCorporate $record): float => (float) $record->total_bayar + (float) $record->total_bayar_langganan)
                    ->formatStateUsing(fn ($state, PelangganCorporate $record): string => 'Rp ' . number_format((float) $record->total_bayar + (float) $record->total_bayar_langganan, 0, ',', '.')),
                TextColumn::make('sisa')
                    ->label('Total Hutang')
                    ->color('danger')
                    ->getStateUsing(fn (PelangganCorporate $record): float => ((float) $record->total_tagihan - (float) $record->total_bayar) + ((float) $record->total_langganan - (float) $record->total_bayar_langganan))
                    ->formatStateUsing(function ($state, PelangganCorporate $record): string {
                        $sisa = ((float) $record->total_tagihan - (float) $record->total_bayar) + ((float) $record->total_langganan - (float) $record->total_bayar_langganan);

                        return 'Rp ' . number_format($sisa, 0, ',', '.');
                    }),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->modalHeading(fn (PelangganCorporate $record): string => 'Rincian Hutang - ' . $record->name)
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalContent(fn (PelangganCorporate $record) => view('components.customer-debt-detail', $this->getDetailData($record))),
            ])
            ->defaultSort('name');
    }

    protected function getDetailData(PelangganCorporate $record): array
    {
        return [
            'customer' => $record,
            'sales' => $record->sales()->with('journalTransactions')->get()
                ->filter(fn (Sale $sale): bool => $sale->sisa > 0)
                ->values(),
            'subscriptions' => $record->subscriptionInvoices()->with('journalTransactions')->get()
                ->filter(fn (SubscriptionInvoice $invoice): bool => $invoice->sisa > 0)
                ->values(),
        ];
    }
}