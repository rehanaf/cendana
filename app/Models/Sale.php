<?php

namespace App\Models;

use App\Support\PaymentDescription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'invoice_no',
        'date',
        'due_date',
        'customer_id',
        'coa_id',
        'wallet_id',
        'total',
        'marketing_cost',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
            'total' => 'decimal:2',
            'marketing_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Sale $sale) {
            $sale->syncMarketingCostTransaction();
        });

        static::deleting(function (Sale $sale) {
            $sale->journalTransactions()->get()->each->delete();

            Transaction::query()
                ->where('marketing_for_sale_id', $sale->getKey())
                ->get()
                ->each->delete();
        });
    }

    /**
     * Sinkronkan transaksi biaya marketing untuk nota ini.
     *
     * Tidak di-link ke sale_id (agar tidak merusak perhitungan total_paid /
     * status lunas), cukup ditandai lewat marketing_for_sale_id.
     */
    public function syncMarketingCostTransaction(): void
    {
        $cost = (float) $this->marketing_cost;
        $existing = Transaction::query()
            ->where('marketing_for_sale_id', $this->getKey())
            ->first();

        if ($cost <= 0) {
            $existing?->delete();

            return;
        }

        $coaId = (int) Setting::get('coa_marketing_id')
            ?: (int) Coa::query()->where('code', '60410')->value('id');

        $coa = $coaId ? Coa::find($coaId) : null;

        if (! $coa || $coa->category === 'transfer') {
            $existing?->delete();

            return;
        }

        $walletId = $this->wallet_id
            ?? Transaction::query()->where('sale_id', $this->getKey())->value('wallet_id')
            ?? Setting::getWalletId('wallet_penjualan_id');

        if (! $walletId) {
            return;
        }

        $attributes = [
            'name' => 'Biaya Marketing • '.$this->invoice_no,
            'user_id' => (auth()->check() ? auth()->id() : null)
                ?? $this->created_by
                ?? User::query()->value('id'),
            'wallet_id' => $walletId,
            'coa_id' => $coa->id,
            'amount' => $cost,
            'description' => PaymentDescription::make('Biaya Marketing', 'penjualan', $this->notes, $this->customer?->name),
            'transaction_date' => $this->date?->format('Y-m-d') ?: now()->toDateString(),
        ];

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        Transaction::query()->create($attributes + ['marketing_for_sale_id' => $this->getKey()]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PelangganCorporate::class, 'customer_id');
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'sale_id');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->journalTransactions()->sum('amount');
    }

    public function getSisaAttribute(): float
    {
        return max(0, (float) $this->total - $this->total_paid);
    }

    public function getIsLunasAttribute(): bool
    {
        return $this->sisa <= 0;
    }

    public function refreshStatus(): void
    {
        $this->updateQuietly([
            'status' => $this->is_lunas ? 'lunas' : 'berjalan',
        ]);
    }
}
