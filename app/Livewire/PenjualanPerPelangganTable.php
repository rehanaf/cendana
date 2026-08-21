<?php

namespace App\Livewire;

use App\Models\RetailInvoice;
use App\Models\Sale;
use App\Models\SubscriptionInvoice;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class PenjualanPerPelangganTable extends TableComponent
{
    public string $source = 'corporate';

    public ?string $mode = 'semua';

    public ?string $date = null;

    public ?string $reportMonth = null;

    public ?string $reportYear = null;

    public ?string $periodStart = null;

    public ?string $periodEnd = null;

    #[On('update-report-filters')]
    public function updateFilters(array $filters): void
    {
        $this->mode = $filters['mode'] ?? $this->mode;
        $this->date = $filters['date'] ?? $this->date;
        $this->reportMonth = $filters['reportMonth'] ?? $this->reportMonth;
        $this->reportYear = $filters['reportYear'] ?? $this->reportYear;
        $this->periodStart = $filters['periodStart'] ?? $this->periodStart;
        $this->periodEnd = $filters['periodEnd'] ?? $this->periodEnd;

        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getQuery())
            ->defaultKeySort(false)
            ->columns($this->getColumns())
            ->actions([
                Action::make('view')
                    ->label('Lihat Invoice')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->modalHeading(fn ($record): string => 'Invoice '.($record?->nama ?? '-'))
                    ->modalContent(fn ($record): View => view('livewire.invoice-list', [
                        'invoices' => $record ? $this->getRelatedInvoices((string) $record->nama) : [],
                        'money' => fn (float $value): string => $this->money($value),
                    ])),
            ])
            ->recordAction('view');
    }

    protected function getQuery(): Builder
    {
        $union = match ($this->source) {
            'corporate' => $this->salesSub()->unionAll($this->subscriptionSub()),
            'retail' => $this->retailSub(),
            default => $this->retailSub(),
        };

        $query = PenjualanPerPelangganRecord::query()
            ->fromSub($union, 'penjualan')
            ->select([
                'nama',
                'nama as id',
                ...($this->source === 'corporate'
                    ? [
                        DB::raw("SUM(CASE WHEN sumber = 'Penjualan' THEN total ELSE 0 END) as total_penjualan"),
                        DB::raw("SUM(CASE WHEN sumber = 'Langganan' THEN total ELSE 0 END) as total_langganan"),
                    ]
                    : []),
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(paid) as dibayar'),
                DB::raw('SUM(total) - SUM(paid) as sisa'),
            ])
            ->groupBy('nama');

        $query = $this->applyModeFilter($query, 'date');

        return $query;
    }

    protected function applyModeFilter(Builder $query, string $dateColumn = 'date'): Builder
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

    protected function salesSub()
    {
        return DB::table('sales as s')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 's.customer_id')
            ->select([
                DB::raw('COALESCE(c.name, \'-\') as nama'),
                DB::raw("'Penjualan' as sumber"),
                's.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sale_id = s.id) as paid'),
                's.date',
            ]);
    }

    protected function subscriptionSub()
    {
        return DB::table('subscription_invoices as si')
            ->leftJoin('corporate_customers as c', 'c.id', '=', 'si.customer_id')
            ->select([
                DB::raw('COALESCE(c.name, \'-\') as nama'),
                DB::raw("'Langganan' as sumber"),
                'si.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.subscription_invoice_id = si.id) as paid'),
                'si.date',
            ]);
    }

    protected function retailSub()
    {
        return DB::table('retail_invoices as ri')
            ->leftJoin('retail_customers as rc', 'rc.id', '=', 'ri.retail_customer_id')
            ->select([
                DB::raw('COALESCE(rc.name, \'-\') as nama'),
                'ri.total',
                DB::raw('(SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.retail_invoice_id = ri.id) as paid'),
                'ri.date',
            ]);
    }

    protected function getColumns(): array
    {
        return [
            TextColumn::make('nama')
                ->label('Pelanggan')
                ->searchable()
                ->sortable(),
            ...($this->source === 'corporate'
                ? [
                    TextColumn::make('total_penjualan')
                        ->label('Total Penjualan')
                        ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                        ->sortable(),
                    TextColumn::make('total_langganan')
                        ->label('Total Langganan')
                        ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                        ->sortable(),
                ]
                : []),
            TextColumn::make('total')
                ->label('Total')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->sortable(),
            TextColumn::make('dibayar')
                ->label('Dibayar')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color('success'),
            TextColumn::make('sisa')
                ->label('Sisa')
                ->formatStateUsing(fn ($state): string => $this->money((float) $state))
                ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
        ];
    }

    protected function money(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return $record['nama'] ?? throw new \LogicException('Record arrays must have a unique [nama] entry for identification.');
        }

        return (string) $record->nama;
    }

    protected function resolveTableRecord(?string $key): Model|array|null
    {
        if ($key === null) {
            return null;
        }

        // Safely get the record by nama, handling potential nulls.
        $record = $this->getQuery()->get()->first(function ($record) use ($key) {
            // Ensure nama is treated as a string and handle nulls gracefully.
            return trim($record->nama ?? '') === trim($key);
        });

        if ($record instanceof Model) {
            $record->setAttribute($record->getKeyName(), $record->nama);
        }

        return $record;
    }

    protected function getRelatedInvoices(string $nama): array
    {
        $modeFilter = function (Builder $query): Builder {
            return $this->applyModeFilter($query, 'date');
        };

        if ($this->source === 'corporate') {
            $sales = $modeFilter(
                Sale::query()
                    ->whereHas('customer', fn (Builder $q) => $q->where('name', $nama))
            )
                ->with('customer')
                ->orderBy('date', 'desc')
                ->get()
                ->map(fn (Sale $sale): array => [
                    'no' => $sale->invoice_no,
                    'sumber' => 'Penjualan',
                    'date' => $sale->date?->format('d M Y'),
                    'total' => (float) $sale->total,
                    'dibayar' => $sale->total_paid,
                ]);

            $langganan = $modeFilter(
                SubscriptionInvoice::query()
                    ->whereHas('customer', fn (Builder $q) => $q->where('name', $nama))
            )
                ->with('customer')
                ->orderBy('date', 'desc')
                ->get()
                ->map(fn (SubscriptionInvoice $invoice): array => [
                    'no' => $invoice->invoice_no,
                    'sumber' => 'Langganan',
                    'date' => $invoice->date?->format('d M Y'),
                    'total' => (float) $invoice->total,
                    'dibayar' => $invoice->total_paid,
                ]);

            return collect($sales)->merge($langganan)->sortByDesc('date')->values()->all();
        }

        return $modeFilter(
            RetailInvoice::query()
                ->whereHas('customer', fn (Builder $q) => $q->where('name', $nama))
        )
            ->with('customer')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn (RetailInvoice $invoice): array => [
                'no' => $invoice->invoice_no,
                'sumber' => 'Langganan',
                'date' => $invoice->date?->format('d M Y'),
                'total' => (float) $invoice->total,
                'dibayar' => $invoice->total_paid,
            ])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.penjualan-per-pelanggan-table');
    }
}

class PenjualanPerPelangganRecord extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
}
