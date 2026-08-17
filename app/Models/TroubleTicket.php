<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TroubleTicket extends Model
{
    protected $fillable = [
        'date',
        'retail_customer_id',
        'description',
        'start_time',
        'restored_time',
        'category',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime',
            'restored_time' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PelangganRetail::class, 'retail_customer_id');
    }

    public function pics(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trouble_ticket_pics');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TroubleTicketStep::class)->orderBy('sort');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
