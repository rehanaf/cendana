<?php

namespace App\Filament\Pages\Concerns;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

trait HasReportFilters
{
    public ?string $mode = 'harian';

    public ?string $date = null;

    public ?string $reportMonth = null;

    public ?string $reportYear = null;

    public ?string $periodStart = null;

    public ?string $periodEnd = null;

    public function mountReportFilters(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->reportMonth = now()->format('m');
        $this->reportYear = now()->format('Y');
        $this->periodStart = now()->startOfMonth()->format('Y-m-d');
        $this->periodEnd = now()->format('Y-m-d');
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

    protected function reportDatePicker(): DatePicker
    {
        return DatePicker::make('date')
            ->hiddenLabel()
            ->native(false)
            ->live()
            ->afterStateUpdated(fn () => $this->dispatchReportFiltersUpdated());
    }

    protected function reportMonthSelect(): Select
    {
        return Select::make('reportMonth')
            ->hiddenLabel()
            ->native(true)
            ->options([
                '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
            ])
            ->live()
            ->afterStateUpdated(fn () => $this->dispatchReportFiltersUpdated());
    }

    protected function reportYearSelect(): Select
    {
        return Select::make('reportYear')
            ->hiddenLabel()
            ->native(true)
            ->options(fn (): array => collect(range(now()->year, now()->year - 5))
                ->mapWithKeys(fn ($y) => [(string) $y => (string) $y])
                ->toArray()
            )
            ->live()
            ->afterStateUpdated(fn () => $this->dispatchReportFiltersUpdated());
    }

    protected function reportPeriodStart(): DatePicker
    {
        return DatePicker::make('periodStart')
            ->hiddenLabel()
            ->native(false)
            ->live()
            ->afterStateUpdated(fn () => $this->dispatchReportFiltersUpdated());
    }

    protected function reportPeriodEnd(): DatePicker
    {
        return DatePicker::make('periodEnd')
            ->hiddenLabel()
            ->native(false)
            ->live()
            ->afterStateUpdated(fn () => $this->dispatchReportFiltersUpdated());
    }

    protected function reportFilterComponents(): array
    {
        return match ($this->mode) {
            'harian' => [
                $this->reportModeSelect(),
                $this->reportDatePicker(),
            ],
            'bulanan' => [
                $this->reportModeSelect(),
                $this->reportMonthSelect(),
                $this->reportYearSelect(),
            ],
            'periode' => [
                $this->reportModeSelect(),
                $this->reportPeriodStart(),
                $this->reportPeriodEnd(),
            ],
            default => [
                $this->reportModeSelect(),
            ],
        };
    }

    protected function dispatchReportFiltersUpdated(): void
    {
        $this->dispatch('refresh-table');
        $this->dispatch('update-report-filters', [
            'mode' => $this->mode,
            'date' => $this->date,
            'reportMonth' => $this->reportMonth,
            'reportYear' => $this->reportYear,
            'periodStart' => $this->periodStart,
            'periodEnd' => $this->periodEnd,
        ]);
    }

    protected function applyModeFilter(Builder|EloquentBuilder $query, string $dateColumn = 'date'): Builder|EloquentBuilder
    {
        if ($this->mode === 'harian' && $this->date) {
            return $query->whereDate($dateColumn, $this->date);
        }

        if ($this->mode === 'bulanan') {
            return $query
                ->whereYear($dateColumn, (int) $this->reportYear)
                ->whereMonth($dateColumn, (int) $this->reportMonth);
        }

        if ($this->mode === 'periode' && $this->periodStart && $this->periodEnd) {
            return $query
                ->whereDate($dateColumn, '>=', $this->periodStart)
                ->whereDate($dateColumn, '<=', $this->periodEnd);
        }

        return $query;
    }

    protected function asOfDate(): string
    {
        return match ($this->mode) {
            'harian' => $this->date ?? now()->format('Y-m-d'),
            'bulanan' => now()->create($this->reportYear ?: now()->year, $this->reportMonth ?: now()->month, 1)
                ->endOfMonth()->format('Y-m-d'),
            default => $this->periodEnd ?? now()->format('Y-m-d'),
        };
    }
}
