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
    public ?string $coaType = '';
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
                Section::make('Ringkasan Keuangan Bulanan Berdasarkan COA')
                    ->description('Rincian kinerja operasional dan mutasi kas berdasarkan Chart of Accounts (COA)')
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
                Section::make('Rincian Transaksi Sesuai COA')
                    ->description('Daftar jurnal mutasi per tanggal, dompet, dan klasifikasi akun COA')
                    ->afterHeader([
                        Select::make('coaType')
                            ->hiddenLabel()
                            ->native(true)
                            ->options([
                                '' => 'Semua Tipe COA',
                                'income' => 'Pendapatan',
                                'cogs' => 'HPP / Pembelian',
                                'expense' => 'Beban Operasional',
                                'tax' => 'Pajak',
                                'asset' => 'Aset',
                                'liability' => 'Kewajiban',
                                'equity' => 'Modal',
                            ])
                            ->live()
                            ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
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

        $income = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('type', 'income'))->sum('amount');
        $cogs = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('type', 'cogs'))->sum('amount');
        $expense = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('type', 'expense'))->sum('amount');
        $tax = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('type', 'tax'))->sum('amount');
        $labaOperasional = $income - $cogs - $expense - $tax;

        $asset = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('type', 'asset')->where('category', 'pengeluaran'))->sum('amount');
        $liability = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('type', 'liability')->where('category', 'pengeluaran'))->sum('amount');
        $equity = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('type', 'equity')->where('category', 'pengeluaran'))->sum('amount');

        return Grid::make(['default' => 2, 'sm' => 2, 'md' => 4, 'lg' => 4])
            ->schema([
                Stat::make('Pendapatan Usaha', 'Rp ' . number_format($income, 0, ',', '.'))
                    ->description('Tipe: income')
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->color('success'),
                Stat::make('HPP / Pembelian', 'Rp ' . number_format($cogs, 0, ',', '.'))
                    ->description('Tipe: cogs')
                    ->descriptionIcon('heroicon-m-shopping-bag')
                    ->color('warning'),
                Stat::make('Beban Operasional', 'Rp ' . number_format($expense, 0, ',', '.'))
                    ->description('Tipe: expense')
                    ->descriptionIcon('heroicon-m-arrow-trending-down')
                    ->color('danger'),
                Stat::make('Beban Pajak', 'Rp ' . number_format($tax, 0, ',', '.'))
                    ->description('Tipe: tax')
                    ->descriptionIcon('heroicon-m-receipt-percent')
                    ->color('danger'),
                Stat::make('Laba Bersih Operasional', 'Rp ' . number_format($labaOperasional, 0, ',', '.'))
                    ->description('Pendapatan - HPP - Beban - Pajak')
                    ->descriptionIcon($labaOperasional >= 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                    ->color($labaOperasional >= 0 ? 'success' : 'danger'),
                Stat::make('Pengeluaran Aset', 'Rp ' . number_format($asset, 0, ',', '.'))
                    ->description('Tipe: asset (Peralatan, dll)')
                    ->descriptionIcon('heroicon-m-building-office')
                    ->color('info'),
                Stat::make('Pembayaran Utang', 'Rp ' . number_format($liability, 0, ',', '.'))
                    ->description('Tipe: liability')
                    ->descriptionIcon('heroicon-m-banknotes')
                    ->color('warning'),
                Stat::make('Prive / Modal', 'Rp ' . number_format($equity, 0, ',', '.'))
                    ->description('Tipe: equity')
                    ->descriptionIcon('heroicon-m-user')
                    ->color('gray'),
            ]);
    }

    public function getDailyStatsGrid(): Grid
    {
        $date = $this->date ?? now()->format('Y-m-d');
        $walletId = $this->walletId ? (int) $this->walletId : null;
        $coaType = $this->coaType ? $this->coaType : null;

        $query = Transaction::query()
            ->when($walletId, fn ($q) => $q->where('wallet_id', $walletId))
            ->when($date, fn ($q) => $q->whereDate('transaction_date', $date))
            ->when($coaType, fn ($q) => $q->whereHas('coa', fn ($cq) => $cq->where('type', $coaType)));

        $income = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('type', 'income'))->sum('amount');
        $cogs = (float) (clone $query)->whereHas('coa', fn ($q) => $q->where('type', 'cogs'))->sum('amount');
        $expense = (float) (clone $query)->whereHas('coa', fn ($q) => $q->whereIn('type', ['expense', 'tax']))->sum('amount');

        $saldoAwal = $this->getSaldo($date, $walletId, before: true);
        $saldoAkhir = $this->getSaldo($date, $walletId, before: false);

        return Grid::make(['default' => 2, 'sm' => 3, 'lg' => 5])
            ->schema([
                Stat::make('Pendapatan', 'Rp ' . number_format($income, 0, ',', '.'))
                    ->description('COA: Pendapatan')
                    ->color('success'),
                Stat::make('HPP / Pembelian', 'Rp ' . number_format($cogs, 0, ',', '.'))
                    ->description('COA: HPP / Pembelian')
                    ->color('warning'),
                Stat::make('Beban & Pajak', 'Rp ' . number_format($expense, 0, ',', '.'))
                    ->description('COA: Beban & Pajak')
                    ->color('danger'),
                Stat::make('Saldo Awal Dompet', 'Rp ' . number_format($saldoAwal, 0, ',', '.'))
                    ->description('Posisi awal hari')
                    ->color('info'),
                Stat::make('Saldo Akhir Dompet', 'Rp ' . number_format($saldoAkhir, 0, ',', '.'))
                    ->description('Posisi akhir hari')
                    ->color('success'),
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
                ->with(['coa', 'wallet'])
                ->when($this->date, fn ($q) => $q->whereDate('transaction_date', $this->date))
                ->when($this->walletId, fn ($q) => $q->where(function ($q) {
                    $q->where('wallet_id', (int) $this->walletId)
                        ->orWhere('to_wallet_id', (int) $this->walletId);
                }))
                ->when($this->coaType, fn ($q) => $q->whereHas('coa', fn ($cq) => $cq->where('type', $this->coaType)))
            )
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('wallet.name')
                    ->label('Dompet')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('coa.code')
                    ->label('Kode COA')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('coa.name')
                    ->label('Nama Akun COA')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('coa.type')
                    ->label('Tipe COA')
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
                TextColumn::make('coa.category')
                    ->label('Arus Kas')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pemasukan' => 'Kas Masuk',
                        'pengeluaran' => 'Kas Keluar',
                        'transfer' => 'Transfer',
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pemasukan' => 'success',
                        'pengeluaran' => 'danger',
                        'transfer' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(35)
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR', decimalPlaces: 0)
                    ->color(fn (Transaction $record): string => match ($record->coa?->category) {
                        'pemasukan' => 'success',
                        'pengeluaran' => 'danger',
                        'transfer' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->defaultSort('transaction_date', 'desc');
    }
}
