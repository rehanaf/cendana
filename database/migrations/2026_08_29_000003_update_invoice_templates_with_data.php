<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->dropColumn('view_name');

            $table->string('company_name')->nullable()->after('name');
            $table->text('company_address')->nullable()->after('company_name');
            $table->text('footer_text')->nullable()->after('company_address');
            $table->string('logo_image')->nullable()->after('footer_text');
            $table->string('logo_width')->nullable()->after('logo_image');
            $table->string('logo_height')->nullable()->after('logo_width');
            $table->string('signature_image')->nullable()->after('logo_height');
            $table->string('signature_width')->nullable()->after('signature_image');
            $table->string('signature_height')->nullable()->after('signature_width');
        });

        DB::table('invoice_templates')
            ->where('id', 1)
            ->update([
                'company_name' => 'CV. ARTHAMULYA SYSTEMA',
                'company_address' => "Jl. Cendana Raya No.13 Bumi Arumsari\nKec. Talun Kab. Cirebon 45171",
                'footer_text' => "Pembayaran dapat dilakukan dengan tunai atau transfer ke rekening bank yang terdaftar\ndengan alamat sebagai berikut:\nBank Mandiri No. Rek : 1340018786110 A.n Dwi Yuliarto",
            ]);
    }

    public function down(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->string('view_name')->default('invoice')->after('name');
            $table->dropColumn([
                'company_name',
                'company_address',
                'footer_text',
                'logo_image',
                'logo_width',
                'logo_height',
                'signature_image',
                'signature_width',
                'signature_height',
            ]);
        });
    }
};