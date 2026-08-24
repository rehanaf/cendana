<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TroubleTicket extends Model
{
    public const CATEGORIES = [
        'ringan' => 'Ringan',
        'sedang' => 'Sedang',
        'berat' => 'Berat',
        'kritis' => 'Kritis',
    ];

    public const STATUSES = [
        'progress' => 'On Proses',
        'closed' => 'Closed',
    ];

    public const HANDLING_METHODS = [
        'remote' => 'By Remote',
        'home_visit' => 'Home Visit',
        'odp_dc' => 'Cek ODP/DC ke Lapangan',
    ];

    public const PIC_TEAMS = [
        'noc' => 'NOC',
        'teknisi' => 'Teknisi',
        'cs' => 'CS',
    ];

    protected $fillable = [
        'date',
        'retail_customer_id',
        'description',
        'start_time',
        'restored_time',
        'category',
        'handling_method',
        'status',
        'pic_teams',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime',
            'restored_time' => 'datetime',
            'pic_teams' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PelangganRetail::class, 'retail_customer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
