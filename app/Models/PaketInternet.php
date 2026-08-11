<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaketInternet extends Model
{
    protected $table = 'internet_packages';

    protected $fillable = [
        'name',
        'speed',
        'price',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function pelangganRetails(): HasMany
    {
        return $this->hasMany(PelangganRetail::class, 'internet_package_id');
    }
}