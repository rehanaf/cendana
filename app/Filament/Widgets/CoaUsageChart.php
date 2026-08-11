<?php

namespace App\Filament\Widgets;

use App\Models\Coa;
use App\Models\Transaction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Filament\Widgets\PieChartWidget;

class CoaUsageChart extends PieChartWidget
{
    use HasFiltersSchema;

    public function getHeading(): string
    {
        return 'Penggunaan COA';
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('month')
                    ->label('Bulan')
                    ->native(true)
                    ->options([
                        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                        '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
                    ])
                    ->default(now()->format('m')),
                Select::make('year')
                    ->label('Tahun')
                    ->native(true)
                    ->options(fn (): array => collect(range(now()->year, now()->year - 5))
                        ->mapWithKeys(fn ($y) => [(string) $y => (string) $y])
                        ->toArray()
                    )
                    ->default(now()->format('Y')),
            ]);
    }

    protected function getData(): array
    {
        $month = $this->filters['month'] ?? now()->format('m');
        $year = $this->filters['year'] ?? now()->format('Y');

        $totals = Transaction::query()
            ->selectRaw('coa_id, count(*) as total')
            ->whereNotNull('coa_id')
            ->whereYear('transaction_date', (int) $year)
            ->whereMonth('transaction_date', (int) $month)
            ->groupBy('coa_id')
            ->pluck('total', 'coa_id')
            ->sortDesc()
            ->take(8);

        if ($totals->isEmpty()) {
            return [
                'labels' => ['Belum ada data'],
                'datasets' => [
                    [
                        'data' => [1],
                        'backgroundColor' => ['#d1d5db'],
                    ],
                ],
            ];
        }

        $coaNames = Coa::whereIn('id', $totals->keys())->pluck('name', 'id');
        $colors = ['#22c55e', '#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

        return [
            'labels' => $totals->keys()->map(fn ($id) => $coaNames->get($id, 'Tanpa COA'))->values()->toArray(),
            'datasets' => [
                [
                    'data' => $totals->values()->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $totals->count()),
                ],
            ],
        ];
    }
}
