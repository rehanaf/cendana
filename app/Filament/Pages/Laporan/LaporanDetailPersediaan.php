<?php

namespace App\Filament\Pages\Laporan;

use App\Models\InventoryItem;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;

class LaporanDetailPersediaan extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Detail Persediaan Barang';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-detail-persediaan';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public function getStatsGrid(): Grid
    {
        $items = InventoryItem::all();

        $totalValue = $items->sum(fn (InventoryItem $item): float => (float) $item->stock * (float) $item->price);
        $toOrder = $items->where('status', 'to_be_order')->count();

        return Grid::make(4)
            ->schema([
                $this->stat('Nilai Persediaan', $totalValue),
                $this->statCount('Jumlah Barang', $items->count()),
                $this->statCount('Total Stok', (int) $items->sum('stock')),
                $this->statCount('Perlu Dipesan', $toOrder, $toOrder > 0 ? 'warning' : 'success'),
            ]);
    }

    protected function getQuery()
    {
        return InventoryItem::query()->orderBy('name');
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label('Nama Barang')
                ->searchable()
                ->sortable(),
            TextColumn::make('price')
                ->label('Harga')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('stock')
                ->label('Stok')
                ->alignRight()
                ->sortable(),
            TextColumn::make('minimum_stock')
                ->label('Stok Minimum')
                ->alignRight()
                ->sortable(),
            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'in_stock' => 'Tersedia',
                    'to_be_order' => 'Perlu Dipesan',
                    default => $state,
                })
                ->color(fn (string $state): string => $state === 'to_be_order' ? 'warning' : 'success'),
            TextColumn::make('nilai')
                ->label('Nilai')
                ->getStateUsing(fn (InventoryItem $record): float => (float) $record->stock * (float) $record->price)
                ->formatStateUsing(fn ($state): string => $this->money((float) $state)),
            TextColumn::make('notes')
                ->label('Catatan')
                ->limit(40)
                ->placeholder('-'),
        ];
    }
}
