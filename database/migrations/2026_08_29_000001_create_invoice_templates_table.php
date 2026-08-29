<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('view_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $templateId = DB::table('invoice_templates')->insertGetId([
            'name' => 'Standar',
            'view_name' => 'invoice',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $settings = [
            ['key' => 'invoice_template_langganan_id', 'value' => (string) $templateId],
            ['key' => 'invoice_template_pembelian_id', 'value' => (string) $templateId],
            ['key' => 'invoice_template_penjualan_id', 'value' => (string) $templateId],
            ['key' => 'invoice_template_retail_id', 'value' => (string) $templateId],
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
            'invoice_template_langganan_id',
            'invoice_template_pembelian_id',
            'invoice_template_penjualan_id',
            'invoice_template_retail_id',
        ])->delete();

        Schema::dropIfExists('invoice_templates');
    }
};