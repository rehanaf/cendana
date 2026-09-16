<?php

namespace App\Models;

use App\Models\Concerns\SyncsMarketingCost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionInvoice extends Model
{
    use SyncsMarketingCost;

    protected $fillable = [
        'invoice_no',
        'customer_id',
        'period',
        'date',
        'due_date',
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
            'period' => 'date',
            'date' => 'date',
            'due_date' => 'date',
            'total' => 'decimal:2',
            'marketing_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (SubscriptionInvoice $invoice) {
            $invoice->journalTransactions()->get()->each->delete();
        });
    }

    public function journalTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'subscription_invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PelangganCorporate::class, 'customer_id');
    }

    protected function marketingTransactionColumn(): string
    {
        return 'marketing_for_subscription_invoice_id';
    }

    protected function marketingWalletSettingKey(): string
    {
        return 'wallet_langganan_id';
    }

    protected function marketingCostColumn(): string
    {
        return 'marketing_cost';
    }

    protected function marketingDescriptionType(): string
    {
        return 'langganan';
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
