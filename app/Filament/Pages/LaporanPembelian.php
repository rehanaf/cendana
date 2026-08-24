<?php

namespace App\Filament\Pages;

use App\Models\Purchase;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LaporanPembelian extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    public ?string $mode = 'harian';
    public ?string $date = null;
    public ?string $reportMonth = null;
    public ?string $reportYear = null;

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Keuangan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return 'heroicon-o-shopping-bag';
    }

    public static function getNavigationLabel(): string
    {
        return 'Laporan Pembelian';
    }

    public function getTitle(): string
    {
        return 'Laporan Pembelian';
    }

    public static function getDefaultSlug(): string
    {
        return 'laporan-pembelian';
    }

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->reportMonth = now()->format('m');
        $this->reportYear = now()->format('Y');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Laporan Pembelian')
                    ->afterHeader([
                        Select::make('mode')
                            ->hiddenLabel()
                            ->native(true)
                            ->options([
                                'harian' => 'Harian',
                                'bulanan' => 'Bulanan',
                            ])
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                        DatePicker::make('date')
                            ->hiddenLabel()
                            ->native(true)
                            ->visible(fn (): bool => $this->mode === 'harian')
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                        Select::make('reportMonth')
                            ->hiddenLabel()
                            ->native(true)
                            ->options([
                                '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                                '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                                '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                                '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
                            ])
                            ->visible(fn (): bool => $this->mode === 'bulanan')
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                        Select::make('reportYear')
                            ->hiddenLabel()
                            ->native(true)
                            ->options(fn (): array => collect(range(now()->year, now()->year - 5))
                                ->mapWithKeys(fn ($y) => [(string) $y => (string) $y])
                                ->toArray()
                            )
                            ->visible(fn (): bool => $this->mode === 'bulanan')
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
        $count = $records->count();

        return Grid::make(4)
            ->schema([
                Stat::make('Total Pembelian', 'Rp ' . number_format($total, 0, ',', '.')),
                Stat::make('Total Dibayar', 'Rp ' . number_format($paid, 0, ',', '.')),
                Stat::make('Belum Dibayar', 'Rp ' . number_format($sisa, 0, ',', '.'))
                    ->color($sisa > 0 ? 'danger' : 'success'),
                Stat::make('Jumlah Nota', (string) $count),
            ]);
    }

    protected function getFilteredQuery()
    {
        return Purchase::query()
            ->with(['vendor'])
            ->when($this->mode === 'harian' && $this->date, fn ($q) => $q->whereDate('date', $this->date))
            ->when($this->mode === 'bulanan', fn ($q) => $q
                ->whereYear('date', (int) $this->reportYear)
                ->whereMonth('date', (int) $this->reportMonth)
            );
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
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
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
                    ->color(fn (Purchase $record): string => $record->sisa > 0 ? 'danger' : 'success'),
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
            ])
            ->defaultSort('date', 'desc');
    }
}
