<?php

namespace App\Models;

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
        static::deleting(function (Sale $sale) {
            $sale->journalTransactions()->get()->each->delete();
        });
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
