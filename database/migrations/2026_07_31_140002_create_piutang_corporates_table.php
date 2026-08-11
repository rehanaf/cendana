<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piutang_corporates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelanggan_corporate_id')->constrained()->cascadeOnDelete();
            $table->date('jatuh_tempo');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('jumlah_bayar', 15, 2)->default(0);
            $table->date('tgl_bayar')->nullable();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('piutang_tak_tertagih')->default(false);
            $table->decimal('biaya_marketing', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piutang_corporates');
    }
};
