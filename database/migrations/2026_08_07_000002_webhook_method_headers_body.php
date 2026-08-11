<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['corporate', 'retail'] as $type) {
            $prefix = "webhook_{$type}_";

            $oldPayload = DB::table('settings')->where('key', "{$prefix}payload")->value('value');

            DB::table('settings')->insertOrIgnore([
                ['key' => "{$prefix}method", 'value' => 'POST'],
                ['key' => "{$prefix}headers", 'value' => ''],
                ['key' => "{$prefix}body", 'value' => $oldPayload ?? ''],
            ]);

            DB::table('settings')->where('key', "{$prefix}payload")->delete();
        }
    }

    public function down(): void
    {
        foreach (['corporate', 'retail'] as $type) {
            $prefix = "webhook_{$type}_";

            $oldBody = DB::table('settings')->where('key', "{$prefix}body")->value('value');

            DB::table('settings')->insertOrIgnore([
                'key' => "{$prefix}payload",
                'value' => $oldBody ?? '',
            ]);

            DB::table('settings')->whereIn('key', [
                "{$prefix}method",
                "{$prefix}headers",
                "{$prefix}body",
            ])->delete();
        }
    }
};