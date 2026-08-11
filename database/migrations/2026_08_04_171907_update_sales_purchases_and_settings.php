<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_payment_id');
        });

        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('subscription_payments');

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('corporate_customers', function (Blueprint $table) {
            $table->renameColumn('kode_pelanggan', 'customer_code');
            $table->renameColumn('nama', 'name');
            $table->renameColumn('alamat', 'address');
            $table->renameColumn('no_telp_pic', 'pic_phone');
            $table->renameColumn('awal_kontrak', 'contract_start');
            $table->renameColumn('is_langganan', 'is_subscription');
            $table->renameColumn('biaya_bulanan', 'monthly_fee');
            $table->renameColumn('tgl_tagih', 'due_day');
        });

        Schema::table('retail_customers', function (Blueprint $table) {
            $table->renameColumn('kode_pelanggan', 'customer_code');
            $table->renameColumn('nama', 'name');
            $table->renameColumn('tgl_mulai_berlangganan', 'subscription_start_date');
            $table->renameColumn('lokasi_blok', 'block_location');
            $table->renameColumn('alamat_lengkap', 'full_address');
            $table->renameColumn('alamat_lahir_ktp', 'ktp_birth_address');
            $table->renameColumn('tgl_lahir_ktp', 'ktp_birth_date');
        });

        Schema::table('internet_packages', function (Blueprint $table) {
            $table->renameColumn('nama', 'name');
            $table->renameColumn('kecepatan', 'speed');
            $table->renameColumn('harga', 'price');
            $table->renameColumn('deskripsi', 'description');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->renameColumn('nama', 'name');
            $table->renameColumn('alamat', 'address');
            $table->renameColumn('no_telp_pic', 'pic_phone');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->renameColumn('no_nota', 'invoice_no');
            $table->renameColumn('tanggal', 'date');
            $table->renameColumn('jatuh_tempo', 'due_date');
            $table->renameColumn('biaya_marketing', 'marketing_cost');
            $table->renameColumn('keterangan', 'notes');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->renameColumn('no_nota', 'invoice_no');
            $table->renameColumn('tanggal', 'date');
            $table->renameColumn('jatuh_tempo', 'due_date');
            $table->renameColumn('keterangan', 'notes');
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->renameColumn('no_invoice', 'invoice_no');
            $table->renameColumn('periode', 'period');
            $table->renameColumn('tanggal', 'date');
            $table->renameColumn('jatuh_tempo', 'due_date');
            $table->renameColumn('keterangan', 'notes');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->renameColumn('notes', 'keterangan');
            $table->renameColumn('due_date', 'jatuh_tempo');
            $table->renameColumn('date', 'tanggal');
            $table->renameColumn('period', 'periode');
            $table->renameColumn('invoice_no', 'no_invoice');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->renameColumn('notes', 'keterangan');
            $table->renameColumn('due_date', 'jatuh_tempo');
            $table->renameColumn('date', 'tanggal');
            $table->renameColumn('invoice_no', 'no_nota');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->renameColumn('notes', 'keterangan');
            $table->renameColumn('marketing_cost', 'biaya_marketing');
            $table->renameColumn('due_date', 'jatuh_tempo');
            $table->renameColumn('date', 'tanggal');
            $table->renameColumn('invoice_no', 'no_nota');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->renameColumn('pic_phone', 'no_telp_pic');
            $table->renameColumn('address', 'alamat');
            $table->renameColumn('name', 'nama');
        });

        Schema::table('internet_packages', function (Blueprint $table) {
            $table->renameColumn('description', 'deskripsi');
            $table->renameColumn('price', 'harga');
            $table->renameColumn('speed', 'kecepatan');
            $table->renameColumn('name', 'nama');
        });

        Schema::table('retail_customers', function (Blueprint $table) {
            $table->renameColumn('ktp_birth_date', 'tgl_lahir_ktp');
            $table->renameColumn('ktp_birth_address', 'alamat_lahir_ktp');
            $table->renameColumn('full_address', 'alamat_lengkap');
            $table->renameColumn('block_location', 'lokasi_blok');
            $table->renameColumn('subscription_start_date', 'tgl_mulai_berlangganan');
            $table->renameColumn('name', 'nama');
            $table->renameColumn('customer_code', 'kode_pelanggan');
        });

        Schema::table('corporate_customers', function (Blueprint $table) {
            $table->renameColumn('due_day', 'tgl_tagih');
            $table->renameColumn('monthly_fee', 'biaya_bulanan');
            $table->renameColumn('is_subscription', 'is_langganan');
            $table->renameColumn('contract_start', 'awal_kontrak');
            $table->renameColumn('pic_phone', 'no_telp_pic');
            $table->renameColumn('address', 'alamat');
            $table->renameColumn('name', 'nama');
            $table->renameColumn('customer_code', 'kode_pelanggan');
        });

        Schema::dropIfExists('settings');

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('subscription_payment_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_invoice_id')->constrained()->cascadeOnDelete();
            $table->date('tgl_bayar');
            $table->decimal('jumlah', 15, 2);
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
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
    }
};
