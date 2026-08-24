<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;

class LaporanUsiaPiutang extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Usia Piutang';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-usia-piutang';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-clock';
    }

    protected function asOf(): string
    {
        return $this->date ?: now()->format('Y-m-d');
    }

    protected function reportFilterComponents(): array
    {
        return [
            DatePicker::make('date')
                ->hiddenLabel()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn () => $this->dispatchReportFiltersUpdated()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(static::reportLabel())
                    ->description('Posisi piutang per ' . Carbon::parse($this->asOf())->format('d F Y'))
                    ->afterHeader($this->reportFilterComponents())
                    ->schema([
                        $this->getStatsGrid(),
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    /**
     * Batas eksklusif (as-of + 1 hari) agar record bertanggal tepat as-of
     * tetap terhitung, apa pun format penyimpanan kolom tanggalnya.
     */
    protected function asOfExclusive(): string
    {
        return Carbon::parse($this->asOf())->addDay()->toDateString();
    }

    public function getStatsGrid(): Grid
    {
        $rows = $this->getQuery()->get();

        $sumBucket = fn (string $bucket) => $rows
            ->where('bucket', $bucket)
            ->sum(fn ($r) => (float) $r->sisa_bayar);

        $totalSisa = $rows->sum(fn ($r) => (float) $r->sisa_bayar);

        return Grid::make(6)
            ->schema([
                $this->stat('Total Piutang', $totalSisa, $totalSisa > 0 ? 'danger' : 'success'),
                $this->stat('Belum Jatuh Tempo', $sumBucket('belum_jatuh_tempo'), 'success'),
                $this->stat('Terlambat 1-30 Hari', $sumBucket('1_30'), 'warning'),
                $this->stat('Terlambat 31-60 Hari', $sumBucket('31_60'), 'orange'),
                $this->stat('Terlambat 61-90 Hari', $sumBucket('61_90'), 'danger'),
                $this->stat('Terlambat > 90 Hari', $sumBucket('over_90'), 'danger'),
            ]);
    }

    protected function salesSub(string $asOf)
    {
        $before = $this->asOfExclusive();

        return DB::table('sales as s')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 's.customer_id')
            ->select([
                's.invoice_no as invoice_no',
                DB::raw("COALESCE(c.name, '-') as customer_name"),
                's.date as date',
                's.due_date as due_date',
                's.total as total',
            ])
            ->selectRaw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sale_id = s.id AND t.transaction_date < ?) as paid', [$before])
            ->whereRaw('s.date < ?', [$before])
            ->whereRaw('s.total > (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sale_id = s.id AND t.transaction_date < ?)', [$before]);
    }

    protected function subscriptionSub(string $asOf)
    {
        $before = $this->asOfExclusive();

        return DB::table('subscription_invoices as si')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 'si.customer_id')
            ->select([
                'si.invoice_no as invoice_no',
                DB::raw("COALESCE(c.name, '-') as customer_name"),
                'si.date as date',
                'si.due_date as due_date',
                'si.total as total',
            ])
            ->selectRaw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = si.id AND t.transaction_date < ?) as paid', [$before])
            ->whereRaw('si.date < ?', [$before])
            ->whereRaw('si.total > (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = si.id AND t.transaction_date < ?)', [$before]);
    }

    protected function retailSub(string $asOf)
    {
        $before = $this->asOfExclusive();

        return DB::table('retail_invoices as ri')
            ->leftJoin('retail_customers as rc', 'rc.id', '=', 'ri.retail_customer_id')
            ->select([
                'ri.invoice_no as invoice_no',
                DB::raw("COALESCE(rc.name, '-') as customer_name"),
                'ri.date as date',
                'ri.due_date as due_date',
                'ri.total as total',
            ])
            ->selectRaw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.retail_invoice_id = ri.id AND t.transaction_date < ?) as paid', [$before])
            ->whereRaw('ri.date < ?', [$before])
            ->whereRaw('ri.total > (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.retail_invoice_id = ri.id AND t.transaction_date < ?)', [$before]);
    }

    protected function getQuery()
    {
        $asOf = $this->asOf();

        $union = $this->salesSub($asOf)
            ->unionAll($this->subscriptionSub($asOf))
            ->unionAll($this->retailSub($asOf));

        [$bucketSql, $bucketBindings] = $this->bucketCase();

        return Transaction::query()
            ->fromSub($union, 'piutang')
            ->select([
                'invoice_no',
                'customer_name',
                'date',
                'due_date',
                'total',
                'paid',
            ])
            ->selectRaw('total - paid as sisa_bayar')
            ->selectRaw($bucketSql . ' as bucket', $bucketBindings)
            ->orderBy('due_date');
    }

    /**
     * Ekspresi SQL penentu bucket aging beserta bindings-nya,
     * dipakai bersama oleh SELECT tabel dan filter status.
     */
    protected function bucketCase(): array
    {
        $asOf = $this->asOf();
        $boundaries = $this->agingBoundaries($asOf);

        return [
            implode(' ', [
                "CASE WHEN due_date >= ? THEN 'belum_jatuh_tempo'",
                "WHEN due_date >= ? THEN '1_30'",
                "WHEN due_date >= ? THEN '31_60'",
                "WHEN due_date >= ? THEN '61_90'",
                "ELSE 'over_90' END",
            ]),
            [
                $asOf,
                $boundaries[30],
                $boundaries[60],
                $boundaries[90],
            ],
        ];
    }

    public static function agingBucketOptions(): array
    {
        return [
            'belum_jatuh_tempo' => 'Belum Jatuh Tempo',
            '1_30' => 'Terlambat 1-30 Hari',
            '31_60' => 'Terlambat 31-60 Hari',
            '61_90' => 'Terlambat 61-90 Hari',
            'over_90' => 'Terlambat > 90 Hari',
        ];
    }

    protected function applyBucketFilter($query, ?string $bucket)
    {
        if (! $bucket) {
            return $query;
        }

        [$sql, $bindings] = $this->bucketCase();

        return $query->whereRaw('(' . $sql . ') = ?', [...$bindings, $bucket]);
    }

    /**
     * Batas tanggal untuk tiap bucket aging, relatif terhadap tanggal as-of.
     * Invoice dengan due_date >= boundary masuk bucket tersebut.
     */
    protected function agingBoundaries(string $asOf): array
    {
        $date = Carbon::parse($asOf);

        return [
            30 => $date->copy()->subDays(30)->toDateString(),
            60 => $date->copy()->subDays(60)->toDateString(),
            90 => $date->copy()->subDays(90)->toDateString(),
        ];
    }

    protected function overdueDays(string $dueDate): int
    {
        return (int) Carbon::parse($dueDate)->diffInDays(Carbon::parse($this->asOf()), false);
    }

    protected function getFilters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('Status Umur')
                ->options(static::agingBucketOptions())
                ->query(fn ($query, array $data) => $this->applyBucketFilter($query, $data['value'] ?? null)),
        ];
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('invoice_no')
                ->label('No. Invoice')
                ->searchable()
                ->sortable(),
            TextColumn::make('customer_name')
                ->label('Pelanggan')
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
            TextColumn::make('paid')
                ->label('Dibayar')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success'),
            TextColumn::make('sisa_bayar')
                ->label('Sisa')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('danger')
                ->sortable(),
            TextColumn::make('umur')
                ->label('Umur (Hari)')
                ->getStateUsing(function ($record): string {
                    $days = $this->overdueDays($record->due_date);

                    if ($days > 0) {
                        return (string) $days;
                    }

                    if ($days === 0) {
                        return 'Hari ini';
                    }

                    return abs($days) . ' hari lagi';
                })
                ->color(fn ($record): string => $record->bucket === 'belum_jatuh_tempo' ? 'success' : 'danger'),
            TextColumn::make('bucket')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (string $state): string => static::agingBucketOptions()[$state] ?? $state)
                ->color(fn (string $state): string => match ($state) {
                    'belum_jatuh_tempo' => 'success',
                    '1_30' => 'warning',
                    '31_60' => 'orange',
                    default => 'danger',
                }),
        ];
    }
}
