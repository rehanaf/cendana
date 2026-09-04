<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coas')) {
            return;
        }

        // 1. Update akun Bandwith Internet (60500) menjadi tipe cogs (HPP / Pembelian)
        DB::table('coas')
            ->where('code', '60500')
            ->update(['type' => 'cogs']);

        // 2. Update akun Prive (30300) menjadi tipe equity (Modal)
        DB::table('coas')
            ->where('code', '30300')
            ->update(['type' => 'equity']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('coas')) {
            return;
        }

        DB::table('coas')
            ->where('code', '60500')
            ->update(['type' => 'expense']);

        DB::table('coas')
            ->where('code', '30300')
            ->update(['type' => 'expense']);
    }
};
