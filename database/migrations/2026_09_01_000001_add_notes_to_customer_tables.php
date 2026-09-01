<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporate_customers', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('due_day');
        });

        Schema::table('retail_customers', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('corporate_customers', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('retail_customers', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
