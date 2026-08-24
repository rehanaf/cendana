<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trouble_tickets', function (Blueprint $table) {
            $table->string('handling_method')->nullable()->after('category');
            $table->json('pic_teams')->nullable()->after('status');
        });

        DB::table('trouble_tickets')
            ->whereIn('status', ['open', 'progress'])
            ->update(['status' => 'progress']);

        DB::table('trouble_tickets')
            ->whereIn('status', ['resolved', 'closed'])
            ->update(['status' => 'closed']);

        Schema::dropIfExists('trouble_ticket_steps');
        Schema::dropIfExists('trouble_ticket_pics');
    }

    public function down(): void
    {
        Schema::table('trouble_tickets', function (Blueprint $table) {
            $table->dropColumn(['handling_method', 'pic_teams']);
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
};
