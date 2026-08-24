<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Coa extends Model
{
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
