<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\LineChartWidget;

class YearlyIncomeExpenseChart extends LineChartWidget
{
    public function getHeading(): string
    {
        return 'Pemasukan & Pengeluaran Tahunan';
    }

    protected function getFilters(): ?array
    {
        $years = collect(range(now()->year, now()->year - 5));
        return $years->mapWithKeys(fn ($y) => [(string) $y => (string) $y])->toArray();
    }

    protected function getData(): array
    {
        $year = (int) ($this->filter ?? now()->year);

        $months = collect(range(1, 12));
        $labels = $months->map(fn ($m) => \Carbon\Carbon::create()->month($m)->format('M'));

        $income = $months->map(fn ($m) => (float) Transaction::whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $m)
            ->whereHas('coa', fn ($q) => $q->where('category', 'pemasukan'))
            ->sum('amount'));

        $expense = $months->map(fn ($m) => (float) Transaction::whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $m)
            ->whereHas('coa', fn ($q) => $q->where('category', 'pengeluaran'))
            ->sum('amount'));

        return [
            'datasets' => [
                [
                    'label' => 'Pemasukan',
                    'data' => $income->toArray(),
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Pengeluaran',
                    'data' => $expense->toArray(),
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels->toArray(),
        ];
    }
}
