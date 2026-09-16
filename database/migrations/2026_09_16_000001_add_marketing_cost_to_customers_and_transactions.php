<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporate_customers', function (Blueprint $table) {
            $table->decimal('marketing_cost', 12, 2)->default(0)->after('monthly_fee');
        });

        Schema::table('retail_customers', function (Blueprint $table) {
            $table->decimal('marketing_cost', 12, 2)->default(0)->after('internet_package_id');
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->decimal('marketing_cost', 12, 2)->default(0)->after('total');
        });

        Schema::table('retail_invoices', function (Blueprint $table) {
            $table->decimal('marketing_cost', 12, 2)->default(0)->after('total');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('marketing_for_subscription_invoice_id')
                ->nullable()
                ->after('subscription_invoice_id')
                ->constrained('subscription_invoices')
                ->nullOnDelete();

            $table->foreignId('marketing_for_retail_invoice_id')
                ->nullable()
                ->after('retail_invoice_id')
                ->constrained('retail_invoices')
                ->nullOnDelete();
        });

        DB::statement('
            UPDATE subscription_invoices
            SET marketing_cost = COALESCE(
                (SELECT amount FROM transactions
                 WHERE marketing_for_subscription_invoice_id = subscription_invoices.id),
                (SELECT marketing_cost FROM corporate_customers
                 WHERE corporate_customers.id = subscription_invoices.customer_id),
                0
            )
        ');

        DB::statement('
            UPDATE retail_invoices
            SET marketing_cost = COALESCE(
                (SELECT amount FROM transactions
                 WHERE marketing_for_retail_invoice_id = retail_invoices.id),
                (SELECT marketing_cost FROM retail_customers
                 WHERE retail_customers.id = retail_invoices.retail_customer_id),
                0
            )
        ');
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketing_for_retail_invoice_id');
            $table->dropConstrainedForeignId('marketing_for_subscription_invoice_id');
        });

        Schema::table('retail_invoices', function (Blueprint $table) {
            $table->dropColumn('marketing_cost');
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropColumn('marketing_cost');
        });

        Schema::table('retail_customers', function (Blueprint $table) {
            $table->dropColumn('marketing_cost');
        });

        Schema::table('corporate_customers', function (Blueprint $table) {
            $table->dropColumn('marketing_cost');
        });
    }
};
