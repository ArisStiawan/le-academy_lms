<?php

/**
 * Set progres enrollment menjadi 100% untuk siswa@test.com dan siswa2@test.com
 * agar lolos syarat pengajuan sertifikat. buyer@test.com sengaja dibiarkan 0%.
 * Jalankan:  php artisan tinker tests/manual/set_progress_100.php
 */

use App\Models\Enrollment;
use App\Models\User;

foreach (['siswa@test.com', 'siswa2@test.com'] as $email) {
    $u = User::where('email', $email)->first();
    Enrollment::where('user_id', $u->id)->update(['progress_pct' => 100]);
    echo "set100={$email}\n";
}
