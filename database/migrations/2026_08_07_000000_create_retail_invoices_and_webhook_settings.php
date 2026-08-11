<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->foreignId('retail_customer_id')->constrained('retail_customers')->cascadeOnDelete();
            $table->foreignId('internet_package_id')->nullable()->constrained('internet_packages')->nullOnDelete();
            $table->date('period');
            $table->date('date');
            $table->date('due_date');
            $table->foreignId('coa_id')->constrained('coas')->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->decimal('total', 15, 2);
            $table->string('status')->default('berjalan');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('retail_invoice_id')->nullable()->constrained()->nullOnDelete();
        });

        $now = now();
        $settings = [
            ['key' => 'coa_retail_id', 'value' => ''],
            ['key' => 'retail_auto_generate', 'value' => '0'],
            ['key' => 'retail_generate_day', 'value' => '1'],
            ['key' => 'webhook_url', 'value' => ''],
            ['key' => 'webhook_secret', 'value' => ''],
            ['key' => 'webhook_enabled', 'value' => '0'],
            ['key' => 'webhook_payload', 'value' => ''],
        ];

        foreach ($settings as $setting) {
            $setting['created_at'] = $now;
            $setting['updated_at'] = $now;

            DB::table('settings')->insertOrIgnore($setting);
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retail_invoice_id');
        });

        Schema::dropIfExists('retail_invoices');

        DB::table('settings')->whereIn('key', [
            'coa_retail_id',
            'retail_auto_generate',
            'retail_generate_day',
            'webhook_url',
            'webhook_secret',
            'webhook_enabled',
            'webhook_payload',
        ])->delete();
    }
};