<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'purchase_id',
        'subscription_invoice_id',
        'retail_invoice_id',
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
        static::created(function (Transaction $transaction) {
            $transaction->recalculateBalance($transaction->wallet_id);
            $transaction->recalculateBalance($transaction->to_wallet_id);
            $transaction->refreshLinkedStatus();
        });

        static::updated(function (Transaction $transaction) {
            $affected = collect([
                $transaction->getOriginal('wallet_id'),
                $transaction->wallet_id,
                $transaction->getOriginal('to_wallet_id'),
                $transaction->to_wallet_id,
            ])->unique()->filter();

            $affected->each(fn ($id) => $transaction->recalculateBalance($id));
            $transaction->refreshLinkedStatus();
        });

        static::deleted(function (Transaction $transaction) {
            $transaction->recalculateBalance($transaction->wallet_id);
            $transaction->recalculateBalance($transaction->to_wallet_id);
            $transaction->refreshLinkedStatus();
        });
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
        if (! $walletId) {
            return;
        }

        $out = self::with('coa')
            ->where('wallet_id', $walletId)
            ->get()
            ->sum(fn ($t) => $t->coa?->category === 'pemasukan' ? $t->amount : -$t->amount);

        $in = self::where('to_wallet_id', $walletId)->sum('amount');

        Wallet::where('id', $walletId)->update(['balance' => $out + $in]);
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
