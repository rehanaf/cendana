<?php

namespace App\Filament\Pages;

use App\Models\SubscriptionInvoice;
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

class LaporanLangganan extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    public ?string $reportMonth = null;
    public ?string $reportYear = null;

    protected static ?int $navigationSort = 4;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return 'heroicon-o-receipt-percent';
    }

    public static function getNavigationLabel(): string
    {
        return 'Laporan Langganan';
    }

    public function getTitle(): string
    {
        return 'Laporan Langganan';
    }

    public static function getDefaultSlug(): string
    {
        return 'laporan-langganan';
    }

    public function mount(): void
    {
        $this->reportMonth = now()->format('m');
        $this->reportYear = now()->format('Y');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Laporan Langganan')
                    ->afterHeader([
                        Select::make('reportMonth')
                            ->hiddenLabel()
                            ->native(true)
                            ->options([
                                '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                                '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                                '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                                '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
                            ])
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                        Select::make('reportYear')
                            ->hiddenLabel()
                            ->native(true)
                            ->options(fn (): array => collect(range(now()->year, now()->year - 5))
                                ->mapWithKeys(fn ($y) => [(string) $y => (string) $y])
                                ->toArray()
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

        $total = $records->sum(fn (SubscriptionInvoice $invoice): float => (float) $invoice->total);
        $paid = $records->sum(fn (SubscriptionInvoice $invoice): float => $invoice->total_paid);
        $sisa = $records->sum(fn (SubscriptionInvoice $invoice): float => $invoice->sisa);
        $count = $records->count();

        return Grid::make(4)
            ->schema([
                Stat::make('Total Tagihan', 'Rp ' . number_format($total, 0, ',', '.')),
                Stat::make('Total Dibayar', 'Rp ' . number_format($paid, 0, ',', '.')),
                Stat::make('Belum Dibayar', 'Rp ' . number_format($sisa, 0, ',', '.'))
                    ->color($sisa > 0 ? 'danger' : 'success'),
                Stat::make('Jumlah Tagihan', (string) $count),
            ]);
    }

    protected function getFilteredQuery()
    {
        return SubscriptionInvoice::query()
            ->with(['customer'])
            ->when($this->reportMonth, fn ($q) => $q->whereMonth('period', (int) $this->reportMonth))
            ->when($this->reportYear, fn ($q) => $q->whereYear('period', (int) $this->reportYear));
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn () => $this->getFilteredQuery())
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('No. Invoice')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
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
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('total_paid')
                    ->label('Dibayar')
                    ->money('IDR', decimalPlaces: 0),
                TextColumn::make('sisa')
                    ->label('Sisa')
                    ->money('IDR', decimalPlaces: 0)
                    ->color(fn (SubscriptionInvoice $record): string => $record->sisa > 0 ? 'danger' : 'success'),
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
            ->defaultSort('period', 'desc');
    }
}
