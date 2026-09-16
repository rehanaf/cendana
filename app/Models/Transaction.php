<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Transaction extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'wallet_id',
        'coa_id',
        'to_wallet_id',
        'amount',
        'description',
        'transaction_reference_id',
        'transaction_date',
        'sale_id',
        'marketing_for_sale_id',
        'purchase_id',
        'subscription_invoice_id',
        'marketing_for_subscription_invoice_id',
        'retail_invoice_id',
        'marketing_for_retail_invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            $transaction->normalizeTransfer();
        });

        static::created(function (Transaction $transaction) {
            static::recalculateWalletBalances([$transaction->wallet_id, $transaction->to_wallet_id]);
            $transaction->refreshLinkedStatus();
        });

        static::updating(function (Transaction $transaction) {
            if ($transaction->isDirty(['coa_id', 'wallet_id', 'to_wallet_id'])) {
                $transaction->normalizeTransfer();
            }
        });

        static::updated(function (Transaction $transaction) {
            $affected = collect([
                $transaction->getOriginal('wallet_id'),
                $transaction->wallet_id,
                $transaction->getOriginal('to_wallet_id'),
                $transaction->to_wallet_id,
            ]);

            static::recalculateWalletBalances($affected->all());
            $transaction->refreshLinkedStatus();
        });

        static::deleted(function (Transaction $transaction) {
            static::recalculateWalletBalances([$transaction->wallet_id, $transaction->to_wallet_id]);
            $transaction->refreshLinkedStatus();
        });
    }

    protected function normalizeTransfer(): void
    {
        $category = $this->coa_id ? Coa::find($this->coa_id)?->category : null;

        if ($category !== 'transfer') {
            $this->to_wallet_id = null;

            if (blank($this->wallet_id)) {
                throw ValidationException::withMessages([
                    'wallet_id' => 'Dompet wajib dipilih untuk transaksi selain transfer.',
                ]);
            }

            return;
        }

        if (blank($this->to_wallet_id)) {
            throw ValidationException::withMessages([
                'to_wallet_id' => 'Dompet tujuan wajib dipilih untuk transaksi transfer.',
            ]);
        }

        if (filled($this->wallet_id) && (int) $this->to_wallet_id === (int) $this->wallet_id) {
            throw ValidationException::withMessages([
                'to_wallet_id' => 'Dompet tujuan harus berbeda dari dompet asal.',
            ]);
        }
    }

    protected function refreshLinkedStatus(): void
    {
        $this->sale?->refreshStatus();
        $this->purchase?->refreshStatus();
        $this->subscriptionInvoice?->refreshStatus();
        $this->retailInvoice?->refreshStatus();
    }

    public function getSumberAttribute(): string
    {
        if ($this->sale_id) {
            return 'Penjualan'.($this->sale ? ' • '.$this->sale->invoice_no : '');
        }

        if ($this->purchase_id) {
            return 'Pembelian'.($this->purchase ? ' • '.$this->purchase->invoice_no : '');
        }

        if ($this->subscription_invoice_id) {
            return 'Langganan'.($this->subscriptionInvoice ? ' • '.$this->subscriptionInvoice->invoice_no : '');
        }

        if ($this->retail_invoice_id) {
            return 'Retail'.($this->retailInvoice ? ' • '.$this->retailInvoice->invoice_no : '');
        }

        return 'Manual';
    }

    public function getSumberTypeAttribute(): string
    {
        if ($this->sale_id) {
            return 'sale';
        }

        if ($this->purchase_id) {
            return 'purchase';
        }

        if ($this->subscription_invoice_id) {
            return 'subscription';
        }

        if ($this->retail_invoice_id) {
            return 'retail';
        }

        return 'manual';
    }

    public function recalculateBalance(?int $walletId): void
    {
        static::recalculateWalletBalances([$walletId]);
    }

    public static function recalculateWalletBalances(array $walletIds): void
    {
        $ids = collect($walletIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            Wallet::query()->whereIn('id', $ids)->lockForUpdate()->pluck('id');

            foreach ($ids as $id) {
                $out = (float) static::query()
                    ->where('wallet_id', $id)
                    ->leftJoin('coas', 'coas.id', '=', 'transactions.coa_id')
                    ->sum(DB::raw("CASE WHEN coas.category = 'pemasukan' THEN transactions.amount ELSE -transactions.amount END"));

                $in = (float) static::query()
                    ->where('to_wallet_id', $id)
                    ->sum('amount');

                Wallet::query()->whereKey($id)->update(['balance' => round($out + $in, 2)]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function subscriptionInvoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }

    public function retailInvoice(): BelongsTo
    {
        return $this->belongsTo(RetailInvoice::class, 'retail_invoice_id');
    }

    public function reference(): BelongsTo
    {
        return $this->belongsTo(TransactionReference::class, 'transaction_reference_id');
    }
}
