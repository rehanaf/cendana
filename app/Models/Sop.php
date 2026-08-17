<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sop extends Model
{
    use HasFactory;

    public const ACCESS_ALL_DIVISIONS = 'all_divisions';
    public const ACCESS_SPECIFIC_DIVISION = 'specific_division';
    public const ACCESS_PUBLIC = 'public';

    protected $fillable = [
        'title',
        'description',
        'file_path',
        'access_type',
        'is_public',
        'role_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Sop $sop) {
            if ($sop->access_type === self::ACCESS_PUBLIC) {
                $sop->is_public = true;
                $sop->role_id = null;
            } elseif ($sop->access_type === self::ACCESS_ALL_DIVISIONS) {
                $sop->is_public = false;
                $sop->role_id = null;
            } elseif ($sop->access_type === self::ACCESS_SPECIFIC_DIVISION) {
                $sop->is_public = false;
            }
        });
    }

    public function isPublic(): bool
    {
        return $this->access_type === self::ACCESS_PUBLIC || $this->is_public;
    }

    public function isAllDivisions(): bool
    {
        return $this->access_type === self::ACCESS_ALL_DIVISIONS;
    }

    public function isSpecificDivision(): bool
    {
        return $this->access_type === self::ACCESS_SPECIFIC_DIVISION;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
