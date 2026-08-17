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
                'harian' => 'Harian',
                'bulanan' => 'Bulanan',
                'periode' => 'Periode',
            ])
            ->live()
            ->afterStateUpdated(fn () => $this->dispatch('refresh-table'));
    }

    protected function reportDatePicker(): DatePicker
    {
        return DatePicker::make('date')
            ->hiddenLabel()
            ->native(true)
            ->visible(fn (): bool => $this->mode === 'harian')
            ->live()
            ->afterStateUpdated(fn () => $this->dispatch('refresh-table'));
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
            ->visible(fn (): bool => $this->mode === 'bulanan')
            ->live()
            ->afterStateUpdated(fn () => $this->dispatch('refresh-table'));
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
            ->visible(fn (): bool => $this->mode === 'bulanan')
            ->live()
            ->afterStateUpdated(fn () => $this->dispatch('refresh-table'));
    }

    protected function reportPeriodStart(): DatePicker
    {
        return DatePicker::make('periodStart')
            ->hiddenLabel()
            ->native(true)
            ->visible(fn (): bool => $this->mode === 'periode')
            ->live()
            ->afterStateUpdated(fn () => $this->dispatch('refresh-table'));
    }

    protected function reportPeriodEnd(): DatePicker
    {
        return DatePicker::make('periodEnd')
            ->hiddenLabel()
            ->native(true)
            ->visible(fn (): bool => $this->mode === 'periode')
            ->live()
            ->afterStateUpdated(fn () => $this->dispatch('refresh-table'));
    }

    protected function reportFilterComponents(): array
    {
        return [
            $this->reportModeSelect(),
            $this->reportDatePicker(),
            $this->reportMonthSelect(),
            $this->reportYearSelect(),
            $this->reportPeriodStart(),
            $this->reportPeriodEnd(),
        ];
    }

    protected function applyModeFilter(Builder | EloquentBuilder $query, string $dateColumn = 'date'): Builder | EloquentBuilder
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
