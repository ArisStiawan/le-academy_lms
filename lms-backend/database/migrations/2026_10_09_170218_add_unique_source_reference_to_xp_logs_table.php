<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('xp_logs', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'source', 'reference_id'],
                'xp_logs_user_source_reference_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xp_logs', function (Blueprint $table) {
            $table->dropUnique(
                'xp_logs_user_source_reference_unique'
            );
        });
    }
};
