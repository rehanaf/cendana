<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('corporate_receivables');

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->text('alamat')->nullable();
            $table->string('pic')->nullable();
            $table->string('no_telp_pic')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('no_nota')->unique();
            $table->date('tanggal');
            $table->date('jatuh_tempo');
            $table->foreignId('customer_id')->constrained('corporate_customers')->cascadeOnDelete();
            $table->foreignId('coa_id')->constrained('coas')->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->decimal('total', 15, 2);
            $table->decimal('biaya_marketing', 15, 2)->default(0);
            $table->string('status')->default('berjalan');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->date('tgl_bayar');
            $table->decimal('jumlah', 15, 2);
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('no_nota')->unique();
            $table->date('tanggal');
            $table->date('jatuh_tempo');
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coa_id')->constrained('coas')->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->decimal('total', 15, 2);
            $table->string('status')->default('berjalan');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->date('tgl_bayar');
            $table->decimal('jumlah', 15, 2);
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_id');
            $table->dropConstrainedForeignId('sale_id');
        });

        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('vendors');
    }
};
