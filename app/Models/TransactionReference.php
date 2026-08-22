<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionReference extends Model
{
    protected $fillable = [
        'reference_no',
        'description',
        'user_id',
    ];

    public static function generateReferenceNo(): string
    {
        do {
            $ref = 'REF-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } while (self::where('reference_no', $ref)->exists());

        return $ref;
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
