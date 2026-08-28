<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class Coa extends Model
{
    public ?Collection $walletsToRecalculate = null;

    protected $table = 'coas';

    protected $fillable = [
        'code',
        'name',
        'type',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $coa): void {
            if (! $coa->isDirty('category')) {
                return;
            }

            $original = $coa->getOriginal('category');

            if ($original !== 'transfer' && $coa->category === 'transfer'
                && $original === 'pemasukan' && $coa->transactions()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'Tidak dapat mengubah kategori menjadi Transfer karena transaksi pemasukan pada COA ini akan kehilangan efek penambahan saldo (transfer tidak menambah uang ke sistem).',
                ]);
            }

            if ($coa->category !== 'transfer') {
                Transaction::query()
                    ->where('coa_id', $coa->getKey())
                    ->whereNotNull('to_wallet_id')
                    ->update(['to_wallet_id' => null]);
            }

            $affected = Transaction::query()
                ->where('coa_id', $coa->getKey())
                ->get(['wallet_id', 'to_wallet_id'])
                ->flatMap(fn (Transaction $t): array => [$t->wallet_id, $t->to_wallet_id])
                ->filter()
                ->unique()
                ->values();

            if ($affected->isNotEmpty()) {
                $coa->walletsToRecalculate = $affected;
            }
        });

        static::updated(function (self $coa): void {
            if ($coa->walletsToRecalculate?->isNotEmpty()) {
                Transaction::recalculateWalletBalances($coa->walletsToRecalculate->all());
                $coa->walletsToRecalculate = null;
            }
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'coa_id');
    }

    public function usageLabels(): Collection
    {
        $labels = collect();

        foreach ([
            'Transaksi' => Transaction::query(),
            'Nota Retail' => RetailInvoice::query(),
            'Penjualan' => Sale::query(),
            'Pembelian' => Purchase::query(),
            'Tagihan Langganan' => SubscriptionInvoice::query(),
        ] as $label => $query) {
            $count = (clone $query)->where('coa_id', $this->getKey())->count();

            if ($count > 0) {
                $labels->push("{$label} ({$count})");
            }
        }

        return $labels;
    }
}
