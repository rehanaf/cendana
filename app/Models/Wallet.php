<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'balance',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function usageLabels(): Collection
    {
        $labels = collect();

        $asSource = Transaction::where('wallet_id', $this->getKey())->count();
        $asDestination = Transaction::where('to_wallet_id', $this->getKey())->count();

        if ($asSource > 0) {
            $labels->push("Transaksi keluar ({$asSource})");
        }

        if ($asDestination > 0) {
            $labels->push("Transfer masuk ({$asDestination})");
        }

        Setting::query()
            ->where('value', (string) $this->getKey())
            ->where('key', 'like', 'wallet%')
            ->pluck('key')
            ->each(fn (string $key) => $labels->push('Pengaturan (' . str_replace('_', ' ', $key) . ')'));

        return $labels;
    }
}
