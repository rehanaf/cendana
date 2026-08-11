<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retail_customers', function (Blueprint $table) {
            $table->string('billing_customer_id')->nullable()->after('nik');
            $table->string('billing_username')->nullable()->after('billing_customer_id');
            $table->string('reference')->nullable()->after('billing_username');
        });
    }

    public function down(): void
    {
        Schema::table('retail_customers', function (Blueprint $table) {
            $table->dropColumn(['reference', 'billing_username', 'billing_customer_id']);
        });
    }
};