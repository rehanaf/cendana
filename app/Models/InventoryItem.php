<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'name',
        'price',
        'minimum_stock',
        'stock',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
            'stock' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InventoryItem $item) {
            $item->status = (float) $item->stock <= (float) $item->minimum_stock
                ? 'to_be_order'
                : 'in_stock';
        });
    }
}
