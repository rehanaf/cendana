<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('coas', 'category')) {
            Schema::table('coas', function (Blueprint $table) {
                $table->string('category')->nullable()->after('type');
            });
        }

        DB::table('coas')
            ->where('type', 'income')
            ->whereNull('category')
            ->update(['category' => 'pemasukan']);

        DB::table('coas')
            ->whereIn('type', ['expense', 'tax'])
            ->whereNull('category')
            ->update(['category' => 'pengeluaran']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('coas', 'category')) {
            Schema::table('coas', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
