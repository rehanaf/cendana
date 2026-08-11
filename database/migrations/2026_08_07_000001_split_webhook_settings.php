<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (['corporate', 'retail'] as $type) {
            foreach ([
                'enabled' => '0',
                'url' => '',
                'secret' => '',
                'payload' => '',
            ] as $suffix => $default) {
                DB::table('settings')->insertOrIgnore([
                    'key' => "webhook_{$type}_{$suffix}",
                    'value' => $default,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('settings')->whereIn('key', [
            'webhook_url',
            'webhook_secret',
            'webhook_enabled',
            'webhook_payload',
        ])->delete();
    }

    public function down(): void
    {
        $now = now();

        DB::table('settings')->whereIn('key', [
            'webhook_corporate_enabled',
            'webhook_corporate_url',
            'webhook_corporate_secret',
            'webhook_corporate_payload',
            'webhook_retail_enabled',
            'webhook_retail_url',
            'webhook_retail_secret',
            'webhook_retail_payload',
        ])->delete();

        foreach ([
            'webhook_enabled' => '0',
            'webhook_url' => '',
            'webhook_secret' => '',
            'webhook_payload' => '',
        ] as $key => $default) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'value' => $default,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};