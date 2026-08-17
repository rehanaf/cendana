<?php

namespace App\Filament\Pages;

use App\Models\Purchase;
use App\Models\Vendor;
use BackedEnum;
use Filament\Forms\Components\Select;
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

class LaporanHutang extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    public ?string $vendorId = '';

    protected static ?int $navigationSort = 6;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Keuangan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return 'heroicon-o-arrow-trending-down';
    }

    public static function getNavigationLabel(): string
    {
        return 'Laporan Hutang';
    }

    public function getTitle(): string
    {
        return 'Laporan Hutang';
    }

    public static function getDefaultSlug(): string
    {
        return 'laporan-hutang';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Laporan Hutang')
                    ->description('Daftar pembelian yang belum lunas')
                    ->afterHeader([
                        Select::make('vendorId')
                            ->hiddenLabel()
                            ->native(true)
                            ->options(fn (): array =>
                                Vendor::orderBy('name')->pluck('name', 'id')->prepend('Semua Vendor', '')->toArray()
                            )
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                    ])
                    ->schema([
                        $this->getStatsGrid(),
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    public function getStatsGrid(): Grid
    {
        $records = $this->getFilteredQuery()->get();

        $total = $records->sum(fn (Purchase $purchase): float => (float) $purchase->total);
        $paid = $records->sum(fn (Purchase $purchase): float => $purchase->total_paid);
        $sisa = $records->sum(fn (Purchase $purchase): float => $purchase->sisa);

        return Grid::make(4)
            ->schema([
                Stat::make('Total Hutang', 'Rp ' . number_format($sisa, 0, ',', '.'))
                    ->color($sisa > 0 ? 'danger' : 'success'),
                Stat::make('Total Pembelian', 'Rp ' . number_format($total, 0, ',', '.')),
                Stat::make('Total Dibayar', 'Rp ' . number_format($paid, 0, ',', '.')),
                Stat::make('Jumlah Nota', (string) $records->count()),
            ]);
    }

    protected function getFilteredQuery()
    {
        return Purchase::query()
            ->with(['vendor'])
            ->when($this->vendorId, fn ($q) => $q->where('vendor_id', (int) $this->vendorId))
            ->whereRaw('total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = purchases.id), 0)');
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn () => $this->getFilteredQuery())
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('No. Nota')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
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
                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('total_paid')
                    ->label('Dibayar')
                    ->money('IDR', decimalPlaces: 0),
                TextColumn::make('sisa')
                    ->label('Sisa')
                    ->money('IDR', decimalPlaces: 0)
                    ->color('danger'),
            ])
            ->defaultSort('date', 'desc');
    }
}
