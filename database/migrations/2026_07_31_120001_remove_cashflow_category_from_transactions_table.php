<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transactions', 'cashflow_category_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cashflow_category_id');
            });
        }

        if (Schema::hasColumn('transactions', 'type')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

        if (Schema::hasTable('cashflow_categories')) {
            Schema::dropIfExists('cashflow_categories');
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('cashflow_category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['income', 'expense', 'transfer']);
        });
    }
};
