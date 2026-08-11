<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporate_customers', function (Blueprint $table) {
            $table->boolean('is_langganan')->default(false);
            $table->decimal('biaya_bulanan', 15, 2)->default(0);
            $table->unsignedTinyInteger('tgl_tagih')->default(1);
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('no_invoice')->unique();
            $table->foreignId('customer_id')->constrained('corporate_customers')->cascadeOnDelete();
            $table->date('periode');
            $table->date('tanggal');
            $table->date('jatuh_tempo');
            $table->foreignId('coa_id')->constrained('coas')->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->decimal('total', 15, 2);
            $table->string('status')->default('berjalan');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
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

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('subscription_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_payment_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_payment_id');
            $table->dropConstrainedForeignId('subscription_invoice_id');
        });

        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_invoices');

        Schema::table('corporate_customers', function (Blueprint $table) {
            $table->dropColumn(['is_langganan', 'biaya_bulanan', 'tgl_tagih']);
        });
    }
};
