<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_invites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('institution_id')
                ->constrained('institutions')
                ->cascadeOnDelete();

            $table->string('email');

            // Token unik untuk link undangan
            $table->string('token', 60)->unique();

            $table->string('role')->default('siswa');

            // Jika diisi, user langsung ter-enroll saat menerima undangan
            $table->foreignId('package_id')
                ->nullable()
                ->constrained('packages')
                ->nullOnDelete();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');

            $table->timestamps();

            // Satu email hanya bisa punya satu undangan aktif per lembaga
            $table->unique(['institution_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_invites');
    }
};
