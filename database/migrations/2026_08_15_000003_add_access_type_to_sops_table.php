<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sops', function (Blueprint $table) {
            $table->string('access_type')->default('all_divisions')->after('file_path');
        });

        // Migrate existing records
        DB::table('sops')->where('is_public', true)->update(['access_type' => 'public']);
        DB::table('sops')->where('is_public', false)->whereNotNull('role_id')->update(['access_type' => 'specific_division']);
        DB::table('sops')->where('is_public', false)->whereNull('role_id')->update(['access_type' => 'all_divisions']);
    }

    public function down(): void
    {
        Schema::table('sops', function (Blueprint $table) {
            $table->dropColumn('access_type');
        });
    }
};
