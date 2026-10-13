<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            // Ambang nilai kelulusan (opsional). Jika null, fallback ke 70% max_score.
            $table->decimal('passing_score', 5, 2)->nullable()->after('max_score');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('passing_score');
        });
    }
};
