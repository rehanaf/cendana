<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Purchase;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanUtangSupplier extends BaseReportPage
{
    public ?string $vendorId = '';

    public static function reportLabel(): string
    {
        return 'Utang Supplier';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-utang-supplier';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-arrow-trending-down';
    }

    protected function reportFilterComponents(): array
    {
        return [
            Select::make('vendorId')
                ->hiddenLabel()
                ->native(true)
                ->options(fn (): array =>
                    \App\Models\Vendor::orderBy('name')
                        ->pluck('name', 'id')
                        ->prepend('Semua Vendor', '')
                        ->toArray()
                )
                ->live()
                ->afterStateUpdated(fn () => $this->dispatch('refresh-table')),
            ...parent::reportFilterComponents(),
        ];
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $total = $rows->sum(fn ($r) => (float) $r->total);
        $paid = $rows->sum(fn ($r) => (float) $r->paid);
        $sisa = $rows->sum(fn ($r) => (float) $r->sisa);

        return Grid::make(4)
            ->schema([
                $this->stat('Total Hutang', $sisa, $sisa > 0 ? 'danger' : 'success'),
                $this->stat('Total Pembelian', $total),
                $this->stat('Total Dibayar', $paid, 'success'),
                $this->statCount('Jumlah Nota', $rows->count()),
            ]);
    }

    protected function getQuery()
    {
        $query = Purchase::query()
            ->leftJoin('vendors as v', 'v.id', '=', 'purchases.vendor_id')
            ->select([
                'purchases.invoice_no as invoice_no',
                DB::raw('COALESCE(v.name, \'\') as vendor_name'),
                'purchases.date as date',
                'purchases.due_date as due_date',
                'purchases.total as total',
                DB::raw('COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = purchases.id), 0) as paid'),
                DB::raw('purchases.total - COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = purchases.id), 0) as sisa'),
            ])
            ->whereRaw('purchases.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = purchases.id), 0)')
            ->when($this->vendorId, fn ($q) => $q->where('purchases.vendor_id', (int) $this->vendorId))
            ->orderBy('purchases.date', 'desc');

        return $query;
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('invoice_no')
                ->label('No. Nota')
                ->searchable()
                ->sortable(),
            TextColumn::make('vendor_name')
                ->label('Vendor')
                ->searchable()
                ->sortable(),
            TextColumn::make('date')
                ->label('Tanggal')
                ->date('d F Y')
                ->sortable(),
            TextColumn::make('due_date')
                ->label('Jatuh Tempo')
                ->date('d F Y')
                ->sortable(),
            TextColumn::make('total')
                ->label('Total')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('paid')
                ->label('Dibayar')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success'),
            TextColumn::make('sisa')
                ->label('Sisa')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('danger')
                ->sortable(),
        ];
    }
}
