<?php

namespace App\Filament\Pages;

use App\Models\Transaction;
use App\Models\Wallet;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LaporanKeuangan extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    public ?string $date = null;
    public ?string $walletId = '';
    public ?string $reportMonth = null;
    public ?string $reportYear = null;

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Keuangan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return 'heroicon-o-document-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return 'Laporan Keuangan';
    }

    public function getTitle(): string
    {
        return 'Laporan Keuangan';
    }

    public static function getDefaultSlug(): string
    {
        return 'laporan-keuangan';
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
                Section::make('Ringkasan Bulanan')
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
                            ->live(),
                        Select::make('reportYear')
                            ->hiddenLabel()
                            ->native(true)
                            ->options(fn (): array => collect(range(now()->year, now()->year - 5))
                                ->mapWithKeys(fn ($y) => [(string) $y => (string) $y])
                                ->toArray()
                            )
                            ->live(),
                    ])
                    ->schema([
                        $this->getMonthlyStatsGrid(),
                    ]),
                Grid::make(2)
                    ->schema([
                        View::make('components.embedded-chart')
                            ->viewData([
                                'widget' => \App\Filament\Widgets\YearlyIncomeExpenseChart::class,
                                'key' => 'yearly-chart',
                            ]),
                        View::make('components.embedded-chart')
                            ->viewData([
                                'widget' => \App\Filament\Widgets\CoaUsageChart::class,
                                'key' => 'coa-chart',
                            ]),
                    ]),
                Section::make('Laporan Harian')
                    ->afterHeader([
                        Select::make('walletId')
                            ->hiddenLabel()
                            ->native(true)
                            ->options(fn (): array =>
                                Wallet::where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->prepend('Semua Dompet', '')
                                    ->toArray()
                            )
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
                        DatePicker::make('date')
                            ->hiddenLabel()
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table'))
                            ->native(true),
                    ])
                    ->schema([
                        $this->getDailyStatsGrid(),
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    public function getMonthlyStatsGrid(): Grid
    {
        $month = $this->reportMonth ? (int) $this->reportMonth : now()->month;
        $year = $this->reportYear ? (int) $this->reportYear : now()->year;

        $query = Transaction::query()
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month);

        $income = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('category', 'pemasukan'))->sum('amount');
        $expense = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('category', 'pengeluaran'))->sum('amount');
        $net = $income - $expense;
        $count = (clone $query)->count();

        $prevDate = \Carbon\Carbon::create($year, $month)->subMonth();
        $prevQuery = Transaction::query()
            ->whereYear('transaction_date', $prevDate->year)
            ->whereMonth('transaction_date', $prevDate->month);

        $prevIncome = (float) (clone $prevQuery)->whereHas('coa', fn ($q) => $q->where('category', 'pemasukan'))->sum('amount');
        $prevExpense = (float) (clone $prevQuery)->whereHas('coa', fn ($q) => $q->where('category', 'pengeluaran'))->sum('amount');
        $prevNet = $prevIncome - $prevExpense;

        $diffIncome = $prevIncome > 0 ? round(($income - $prevIncome) / $prevIncome * 100) : ($income > 0 ? 100 : 0);
        $diffExpense = $prevExpense > 0 ? round(($expense - $prevExpense) / $prevExpense * 100) : ($expense > 0 ? 100 : 0);
        $diffNet = $prevNet > 0 ? round(($net - $prevNet) / $prevNet * 100) : ($net > 0 ? 100 : ($net < 0 ? -100 : 0));

        return Grid::make(3)
            ->schema([
                Stat::make('Pemasukan', 'Rp ' . number_format($income, 0, ',', '.'))
                    ->description(($diffIncome >= 0 ? 'Naik ' : 'Turun ') . abs($diffIncome) . '%')
                    ->descriptionIcon($diffIncome >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                    ->descriptionColor($diffIncome >= 0 ? 'success' : 'danger'),
                Stat::make('Pengeluaran', 'Rp ' . number_format($expense, 0, ',', '.'))
                    ->description(($diffExpense >= 0 ? 'Naik ' : 'Turun ') . abs($diffExpense) . '%')
                    ->descriptionIcon($diffExpense >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                    ->descriptionColor($diffExpense >= 0 ? 'danger' : 'success'),
                Stat::make('Selisih', 'Rp ' . number_format($net, 0, ',', '.'))
                    ->description(($diffNet >= 0 ? 'Naik ' : 'Turun ') . abs($diffNet) . '%')
                    ->descriptionIcon($diffNet >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                    ->descriptionColor($diffNet >= 0 ? 'success' : 'danger'),
                Stat::make('Jumlah Transaksi', (string) $count),
                Stat::make('Rata-rata Pemasukan', 'Rp ' . number_format($count > 0 ? $income / $count : 0, 0, ',', '.')),
                Stat::make('Rata-rata Pengeluaran', 'Rp ' . number_format($count > 0 ? $expense / $count : 0, 0, ',', '.')),
            ]);
    }

    public function getDailyStatsGrid(): Grid
    {
        $date = $this->date ?? now()->format('Y-m-d');
        $walletId = $this->walletId ? (int) $this->walletId : null;

        $query = Transaction::query()
            ->when($walletId, fn ($q) => $q->where('wallet_id', $walletId))
            ->when($date, fn ($q) => $q->whereDate('transaction_date', $date));

        $income = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('category', 'pemasukan'))->sum('amount');
        $expense = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('category', 'pengeluaran'))->sum('amount');
        $net = $income - $expense;

        $prevDate = \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d');
        $prevQuery = Transaction::query()
            ->when($walletId, fn ($q) => $q->where('wallet_id', $walletId))
            ->when($prevDate, fn ($q) => $q->whereDate('transaction_date', $prevDate));

        $prevIncome = (float) (clone $prevQuery)->whereHas('coa', fn ($q) => $q->where('category', 'pemasukan'))->sum('amount');
        $prevExpense = (float) (clone $prevQuery)->whereHas('coa', fn ($q) => $q->where('category', 'pengeluaran'))->sum('amount');

        $diffIncome = $prevIncome > 0 ? round(($income - $prevIncome) / $prevIncome * 100) : ($income > 0 ? 100 : 0);
        $diffExpense = $prevExpense > 0 ? round(($expense - $prevExpense) / $prevExpense * 100) : ($expense > 0 ? 100 : 0);

        $saldoAwal = $this->getSaldo($date, $walletId, before: true);
        $saldoAkhir = $this->getSaldo($date, $walletId, before: false);

        return Grid::make(3)
            ->schema([
                Stat::make('Pemasukan', 'Rp ' . number_format($income, 0, ',', '.'))
                    ->description(($diffIncome >= 0 ? 'Naik ' : 'Turun ') . abs($diffIncome) . '%')
                    ->descriptionIcon($diffIncome >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                    ->descriptionColor($diffIncome >= 0 ? 'success' : 'danger'),
                Stat::make('Pengeluaran', 'Rp ' . number_format($expense, 0, ',', '.'))
                    ->description(($diffExpense >= 0 ? 'Naik ' : 'Turun ') . abs($diffExpense) . '%')
                    ->descriptionIcon($diffExpense >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                    ->descriptionColor($diffExpense >= 0 ? 'danger' : 'success'),
                Stat::make('Selisih', 'Rp ' . number_format($net, 0, ',', '.')),
                Stat::make('Saldo Awal', 'Rp ' . number_format($saldoAwal, 0, ',', '.')),
                Stat::make('Saldo Akhir', 'Rp ' . number_format($saldoAkhir, 0, ',', '.')),
            ]);
    }

    protected function getSaldo(string $date, ?int $walletId, bool $before): float
    {
        $op = $before ? '<' : '<=';

        if (! $walletId) {
            $query = Transaction::query()->whereDate('transaction_date', $op, $date);

            $inflow = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('category', 'pemasukan'))->sum('amount');
            $outflow = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('category', 'pengeluaran'))->sum('amount');

            return $inflow - $outflow;
        }

        $out = Transaction::with('coa')
            ->where('wallet_id', $walletId)
            ->whereDate('transaction_date', $op, $date)
            ->get()
            ->sum(fn ($t) => $t->coa?->category === 'pemasukan' ? $t->amount : -$t->amount);

        $in = (float) Transaction::where('to_wallet_id', $walletId)
            ->whereDate('transaction_date', $op, $date)
            ->sum('amount');

        return $out + $in;
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn () => Transaction::query()
                ->when($this->date, fn ($q) => $q->whereDate('transaction_date', $this->date))
                ->when($this->walletId, fn ($q) => $q->where(function ($q) {
                    $q->where('wallet_id', (int) $this->walletId)
                        ->orWhere('to_wallet_id', (int) $this->walletId);
                }))
            )
            ->columns([
                TextColumn::make('coa.code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('coa.name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(30),
                TextColumn::make('coa.category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pemasukan' => 'Pemasukan',
                        'pengeluaran' => 'Pengeluaran',
                        'transfer' => 'Transfer',
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pemasukan' => 'success',
                        'pengeluaran' => 'danger',
                        'transfer' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('coa.type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'asset' => 'Aset',
                        'liability' => 'Kewajiban',
                        'equity' => 'Modal',
                        'income' => 'Pendapatan',
                        'cogs' => 'HPP / Pembelian',
                        'expense' => 'Beban',
                        'tax' => 'Pajak',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'asset' => 'info',
                        'liability' => 'warning',
                        'equity' => 'success',
                        'income' => 'success',
                        'cogs' => 'warning',
                        'expense' => 'danger',
                        'tax' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR', decimalPlaces: 0)
                    ->color(fn (Transaction $record): string => match ($record->coa?->category) {
                        'pemasukan' => 'success',
                        'pengeluaran' => 'danger',
                        'transfer' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('transaction_date', 'desc');
    }
}
