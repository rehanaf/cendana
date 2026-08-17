<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Purchase;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;

class LaporanUsiaUtang extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Usia Utang';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-usia-utang';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-clock';
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $totalSisa = $rows->sum(fn ($r) => (float) $r->sisa_bayar);
        $lancar = $rows->where('bucket', 'lancar')->sum(fn ($r) => (float) $r->sisa_bayar);
        $terlambat = $totalSisa - $lancar;

        return Grid::make(4)
            ->schema([
                $this->stat('Total Utang', $totalSisa, $totalSisa > 0 ? 'danger' : 'success'),
                $this->stat('Belum Jatuh Tempo', $lancar, 'success'),
                $this->stat('Sudah Jatuh Tempo', $terlambat, $terlambat > 0 ? 'danger' : 'success'),
                $this->statCount('Jumlah Nota', $rows->count()),
            ]);
    }

    protected function getQuery()
    {
        $query = DB::table('purchases as p')
            ->leftJoin('vendors as v', 'v.id', '=', 'p.vendor_id')
            ->select([
                'p.invoice_no as invoice_no',
                DB::raw('COALESCE(v.name, \'-\') as vendor_name'),
                'p.date as date',
                'p.due_date as due_date',
                'p.total as total',
                DB::raw('COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = p.id), 0) as paid'),
            ])
            ->whereRaw('p.total > COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.purchase_id = p.id), 0)');

        $query = Purchase::query()
            ->fromSub($query, 'utang')
            ->select([
                'invoice_no',
                'vendor_name',
                'date',
                'due_date',
                'total',
                'paid',
                DB::raw('total - paid as sisa_bayar'),
                DB::raw('CASE WHEN due_date >= CURRENT_DATE THEN \'lancar\' ELSE \'terlambat\' END as bucket'),
            ])
            ->orderBy('due_date');

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
                ->label('Supplier')
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
            TextColumn::make('sisa_bayar')
                ->label('Sisa')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('danger')
                ->sortable(),
            TextColumn::make('umur')
                ->label('Umur (Hari)')
                ->getStateUsing(fn ($record): int => (int) \Carbon\Carbon::parse($record->due_date)->diffInDays(now(), false))
                ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'success'),
            TextColumn::make('bucket')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (string $state): string => $state === 'lancar' ? 'Belum Jatuh Tempo' : 'Jatuh Tempo')
                ->color(fn (string $state): string => $state === 'lancar' ? 'success' : 'danger'),
        ];
    }
}
