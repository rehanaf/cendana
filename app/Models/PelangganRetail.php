<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PelangganRetail extends Model
{
    protected $table = 'retail_customers';

    protected $fillable = [
        'customer_code',
        'name',
        'email',
        'subscription_start_date',
        'internet_package_id',
        'block_location',
        'full_address',
        'wa',
        'nik',
        'billing_customer_id',
        'billing_username',
        'reference',
        'ktp_birth_address',
        'ktp_birth_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'subscription_start_date' => 'date',
            'ktp_birth_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function paketInternet(): BelongsTo
    {
        return $this->belongsTo(PaketInternet::class, 'internet_package_id');
    }
}