<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelanggan_retails', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pelanggan')->unique();
            $table->string('nama');
            $table->string('email')->nullable();
            $table->date('tgl_mulai_berlangganan')->nullable();
            $table->foreignId('paket_internet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lokasi_blok')->nullable();
            $table->text('alamat_lengkap')->nullable();
            $table->string('wa')->nullable();
            $table->string('nik')->nullable();
            $table->string('alamat_lahir_ktp')->nullable();
            $table->date('tgl_lahir_ktp')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggan_retails');
    }
};
