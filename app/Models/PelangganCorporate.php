<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PelangganCorporate extends Model
{
    protected $table = 'corporate_customers';

    protected $fillable = [
        'customer_code',
        'name',
        'address',
        'pic',
        'pic_phone',
        'contract_start',
        'is_subscription',
        'monthly_fee',
        'marketing_cost',
        'due_day',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'contract_start' => 'date',
            'is_subscription' => 'boolean',
            'monthly_fee' => 'decimal:2',
            'marketing_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function subscriptionInvoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'customer_id');
    }
}
