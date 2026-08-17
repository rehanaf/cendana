<?php

namespace App\Filament\Pages\Laporan;

use App\Models\InventoryItem;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanRingkasanPersediaan extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Ringkasan Persediaan Barang';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-ringkasan-persediaan';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-inbox-stack';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $totalValue = $rows->sum(fn ($r) => (float) $r->nilai);
        $totalStock = $rows->sum(fn ($r) => (float) $r->stok);

        return Grid::make(4)
            ->schema([
                $this->stat('Nilai Persediaan', $totalValue),
                $this->statCount('Total Stok', (int) $totalStock),
                $this->statCount('Jumlah Barang', $rows->count()),
                $this->statCount('Perlu Pesan', $rows->sum(fn ($r) => (int) $r->jumlah_item)),
            ]);
    }

    protected function getQuery()
    {
        return InventoryItem::query()
            ->select([
                'status',
                DB::raw('COUNT(*) as jumlah_item'),
                DB::raw('SUM(stock) as stok'),
                DB::raw('SUM(stock * price) as nilai'),
            ])
            ->groupBy('status')
            ->orderByDesc(DB::raw('SUM(stock * price)'));
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'in_stock' => 'Tersedia',
                    'to_be_order' => 'Perlu Dipesan',
                    default => $state,
                })
                ->color(fn (string $state): string => $state === 'to_be_order' ? 'warning' : 'success'),
            TextColumn::make('jumlah_item')
                ->label('Jumlah Barang')
                ->alignRight()
                ->sortable(),
            TextColumn::make('stok')
                ->label('Total Stok')
                ->alignRight()
                ->sortable(),
            TextColumn::make('nilai')
                ->label('Nilai')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
        ];
    }
}
