<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('paket_internets', 'internet_packages');
        Schema::rename('pelanggan_corporates', 'corporate_customers');
        Schema::rename('piutang_corporates', 'corporate_receivables');
        Schema::rename('pelanggan_retails', 'retail_customers');
    }

    public function down(): void
    {
        Schema::rename('internet_packages', 'paket_internets');
        Schema::rename('corporate_customers', 'pelanggan_corporates');
        Schema::rename('corporate_receivables', 'piutang_corporates');
        Schema::rename('retail_customers', 'pelanggan_retails');
    }
};
