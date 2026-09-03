<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Laporan\LaporanPiutangPelanggan;
use BackedEnum;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LaporanPiutang extends LaporanPiutangPelanggan
{
    protected static ?int $navigationSort = 5;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Keuangan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return 'heroicon-o-arrow-trending-up';
    }

    public static function getNavigationLabel(): string
    {
        return 'Laporan Piutang';
    }

    public function getTitle(): string
    {
        return 'Laporan Piutang';
    }

    public static function getDefaultSlug(): string
    {
        return 'laporan-piutang';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Laporan Piutang')
                    ->description('Daftar piutang dari penjualan, langganan corporate, dan retail yang belum lunas')
                    ->afterHeader($this->reportFilterComponents())
                    ->schema([
                        $this->getStatsGrid(),
                        EmbeddedTable::make(),
                    ]),
            ]);
    }
}
