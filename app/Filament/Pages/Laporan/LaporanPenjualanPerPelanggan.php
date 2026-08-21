<?php

namespace App\Filament\Pages\Laporan;

use App\Filament\Pages\Concerns\HasReportFilters;
use App\Livewire\PenjualanPerPelangganTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LaporanPenjualanPerPelanggan extends Page
{
    use HasReportFilters;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return 'Penjualan Per Pelanggan';
    }

    public function getTitle(): string
    {
        return 'Penjualan Per Pelanggan';
    }

    public static function getDefaultSlug(): string
    {
        return 'laporan-penjualan-per-pelanggan';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-user-group';
    }

    public function mount(): void
    {
        $this->mountReportFilters();
        $this->mode = 'semua';
    }

    protected function reportModeSelect(): Select
    {
        return Select::make('mode')
            ->hiddenLabel()
            ->native(true)
            ->options([
                'semua' => 'Semua',
                'harian' => 'Harian',
                'bulanan' => 'Bulanan',
                'periode' => 'Periode',
            ])
            ->live()
            ->afterStateUpdated(fn () => $this->dispatchReportFiltersUpdated());
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->total);
        $paid = $rows->sum(fn ($r) => (float) $r->dibayar);
        $sisa = $rows->sum(fn ($r) => (float) $r->sisa);

        $corporateRows = $this->getSourceRows(['Penjualan', 'Langganan']);
        $penjualanCorporate = $corporateRows->where('sumber', 'Penjualan')->sum(fn ($r) => (float) $r->total);
        $langgananCorporate = $corporateRows->where('sumber', 'Langganan')->sum(fn ($r) => (float) $r->total);
        $retailRows = $this->getSourceRows(['Retail']);
        $langgananRetail = $retailRows->sum(fn ($r) => (float) $r->total);

        return Grid::make(4)
            ->schema([
                $this->stat('Total Penjualan', $total),
                $this->stat('Penjualan Project / Umum', $penjualanCorporate),
                $this->stat('Penjualan Langganan Corporate', $langgananCorporate),
                $this->stat('Penjualan Langganan Retail', $langgananRetail),
                $this->stat('Total Dibayar', $paid, 'success'),
                $this->stat('Belum Dibayar', $sisa, $sisa > 0 ? 'danger' : 'success'),
                $this->statCount('Jumlah Pelanggan Corporate', $corporateRows->pluck('nama')->unique()->count()),
                $this->statCount('Jumlah Pelanggan Retail', $retailRows->pluck('nama')->unique()->count()),
            ]);
    }

    protected function getSourceRows(array $sources): Collection
    {
        $subs = [];
        if (in_array('Penjualan', $sources, true)) {
            $subs[] = $this->salesSub();
        }
        if (in_array('Langganan', $sources, true)) {
            $subs[] = $this->subscriptionSub();
        }
        if (in_array('Retail', $sources, true)) {
            $subs[] = $this->retailSub();
        }

        $union = array_shift($subs);
        foreach ($subs as $sub) {
            $union = $union->unionAll($sub);
        }

        $query = Transaction::query()
            ->fromSub($union, 'penjualan')
            ->select(['nama', 'sumber', 'total', 'paid']);

        return $this->applyModeFilter($query, 'date')->get();
    }

    public function content(Schema $schema): Schema
    {
        $filterData = [
            'mode' => $this->mode,
            'date' => $this->date,
            'reportMonth' => $this->reportMonth,
            'reportYear' => $this->reportYear,
            'periodStart' => $this->periodStart,
            'periodEnd' => $this->periodEnd,
        ];

        return $schema
            ->components([
                Section::make('Penjualan Per Pelanggan')
                    ->afterHeader($this->reportFilterComponents())
                    ->schema([
                        $this->getStatsGrid(),
                        Tabs::make()->tabs([
                            Tabs\Tab::make('Corporate')
                                ->schema([
                                    $this->embeddedTable('corporate', $filterData),
                                ]),
                            Tabs\Tab::make('Retail')
                                ->schema([
                                    $this->embeddedTable('retail', $filterData),
                                ]),
                        ]),
                    ]),
            ]);
    }

    protected function embeddedTable(string $source, array $filterData): Livewire
    {
        return EmbeddedTable::make(PenjualanPerPelangganTable::class, [
            ...$filterData,
            'source' => $source,
        ])->key($source);
    }

    protected function stat(string $label, float $value, ?string $color = null, ?string $icon = null): Stat
    {
        return Stat::make($label, 'Rp '.number_format($value, 0, ',', '.'))
            ->color($color)
            ->icon($icon);
    }

    protected function statCount(string $label, int $value, ?string $color = null): Stat
    {
        return Stat::make($label, (string) $value)
            ->color($color);
    }

    protected function money(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }

    protected function salesSub()
    {
        return DB::table('sales as s')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 's.customer_id')
            ->select([
                DB::raw('COALESCE(c.name, \'-\') as nama'),
                DB::raw("'Penjualan' as sumber"),
                's.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sale_id = s.id) as paid'),
                's.date',
            ]);
    }

    protected function subscriptionSub()
    {
        return DB::table('subscription_invoices as si')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 'si.customer_id')
            ->select([
                DB::raw('COALESCE(c.name, \'-\') as nama'),
                DB::raw("'Langganan' as sumber"),
                'si.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = si.id) as paid'),
                'si.date',
            ]);
    }

    protected function retailSub()
    {
        return DB::table('retail_invoices as ri')
            ->leftJoin('retail_customers as rc', 'rc.id', '=', 'ri.retail_customer_id')
            ->select([
                DB::raw('COALESCE(rc.name, \'-\') as nama'),
                DB::raw("'Retail' as sumber"),
                'ri.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.retail_invoice_id = ri.id) as paid'),
                'ri.date',
            ]);
    }

    protected function getQuery()
    {
        $union = $this->salesSub()->unionAll($this->subscriptionSub())->unionAll($this->retailSub());

        $query = Transaction::query()
            ->fromSub($union, 'penjualan')
            ->select([
                'nama',
                DB::raw('COUNT(*) as jumlah_nota'),
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(paid) as dibayar'),
                DB::raw('SUM(total) - SUM(paid) as sisa'),
            ])
            ->groupBy('nama');

        $query = $this->applyModeFilter($query, 'date');

        return $query;
    }
}
