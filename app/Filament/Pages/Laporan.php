<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Laporan\LaporanHub;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Laporan extends Page
{
    protected string $view = 'filament.pages.laporan';

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedDocumentChartBar;
    }

    public static function getNavigationLabel(): string
    {
        return 'Laporan';
    }

    public function getTitle(): string
    {
        return 'Laporan';
    }

    public static function getDefaultSlug(): string
    {
        return 'laporan';
    }

    protected function getViewData(): array
    {
        return [
            'categories' => LaporanHub::categories(),
        ];
    }
}
