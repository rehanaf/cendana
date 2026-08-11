<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection()->getDatabaseName();

        $hasForeign = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', 'retail_customers')
            ->where('CONSTRAINT_NAME', 'pelanggan_retails_paket_internet_id_foreign')
            ->exists();

        if ($hasForeign) {
            Schema::table('retail_customers', function (Blueprint $table) {
                $table->dropForeign('pelanggan_retails_paket_internet_id_foreign');
            });
        }

        Schema::table('retail_customers', function (Blueprint $table) {
            $table->renameColumn('paket_internet_id', 'internet_package_id');
        });

        Schema::table('retail_customers', function (Blueprint $table) {
            $table->foreign('internet_package_id')->references('id')->on('internet_packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $db = DB::connection()->getDatabaseName();

        $hasForeign = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', 'retail_customers')
            ->where('CONSTRAINT_NAME', 'retail_customers_internet_package_id_foreign')
            ->exists();

        if ($hasForeign) {
            Schema::table('retail_customers', function (Blueprint $table) {
                $table->dropForeign('retail_customers_internet_package_id_foreign');
            });
        }

        Schema::table('retail_customers', function (Blueprint $table) {
            $table->renameColumn('internet_package_id', 'paket_internet_id');
        });

        Schema::table('retail_customers', function (Blueprint $table) {
            $table->foreign('paket_internet_id')->references('id')->on('internet_packages')->nullOnDelete();
        });
    }
};
