<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            ['key' => 'wallet_penjualan_id', 'value' => ''],
            ['key' => 'wallet_pembelian_id', 'value' => ''],
            ['key' => 'wallet_langganan_id', 'value' => ''],
            ['key' => 'wallet_retail_id', 'value' => ''],
        ];

        foreach ($settings as $setting) {
            $setting['created_at'] = $now;
            $setting['updated_at'] = $now;

            DB::table('settings')->insertOrIgnore($setting);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'wallet_penjualan_id',
            'wallet_pembelian_id',
            'wallet_langganan_id',
            'wallet_retail_id',
        ])->delete();
    }
};