<?php

namespace App\Filament\Pages\Laporan;

use App\Filament\Pages\Concerns\HasReportFilters;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;

abstract class BaseReportPage extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    use HasReportFilters;

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public static function getNavigationLabel(): string
    {
        return static::reportLabel();
    }

    public function getTitle(): string
    {
        return static::reportLabel();
    }

    abstract public static function reportLabel(): string;

    abstract public static function getReportSlug(): string;

    abstract public function getReportIcon(): string | BackedEnum | null;

    public static function getDefaultSlug(): string
    {
        return static::getReportSlug();
    }

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return static::getReportIcon();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(static::reportLabel())
                    ->afterHeader($this->reportFilterComponents())
                    ->schema([
                        View::make('components.report-loading')
                            ->viewData(['targets' => $this->getLoadingTargets()]),
                        $this->getStatsGrid(),
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    abstract public function getStatsGrid(): Grid;

    protected function stat(string $label, float $value, ?string $color = null, ?string $icon = null): Stat
    {
        return Stat::make($label, 'Rp ' . number_format($value, 0, ',', '.'))
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
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    protected function getLoadingTargets(): string
    {
        return 'mode, date, reportMonth, reportYear, periodStart, periodEnd';
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn () => $this->getQuery())
            ->defaultKeySort(false)
            ->columns($this->getColumns())
            ->filters($this->getFilters());
    }

    protected function getFilters(): array
    {
        return [];
    }

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model|array $record): string
    {
        if (is_array($record)) {
            return (string) ($record['invoice_no'] ?? $record['id'] ?? $record['nama'] ?? '');
        }

        return (string) ($record->invoice_no ?? $record->id ?? $record->nama ?? '');
    }

    abstract protected function getQuery();

    abstract protected function getColumns(): array;
}
