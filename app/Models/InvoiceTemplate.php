<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    protected $fillable = [
        'name',
        'company_name',
        'company_address',
        'footer_text',
        'logo_image',
        'logo_width',
        'logo_height',
        'signature_image',
        'signature_width',
        'signature_height',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}