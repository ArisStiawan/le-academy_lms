<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Isi materi (bacaan) agar konten bisa dibuka sebagai halaman baca
     * di dalam aplikasi, bukan hanya tautan keluar.
     */
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->longText('body')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn('body');
        });
    }
};
