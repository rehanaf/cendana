<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trouble_tickets', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('retail_customer_id')->constrained('retail_customers')->cascadeOnDelete();
            $table->text('description');
            $table->dateTime('start_time');
            $table->dateTime('restored_time')->nullable();
            $table->string('category')->default('ringan');
            $table->string('status')->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('trouble_ticket_pics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trouble_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('trouble_ticket_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trouble_ticket_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->integer('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trouble_ticket_steps');
        Schema::dropIfExists('trouble_ticket_pics');
        Schema::dropIfExists('trouble_tickets');
    }
};
