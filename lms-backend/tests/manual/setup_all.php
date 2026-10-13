<?php

/**
 * Seed data lengkap untuk pengujian manual semua fitur LMS.
 * Jalankan:  php artisan tinker tests/manual/setup_all.php
 *
 * Membuat: 1 institusi, 1 module, 1 assessment, 2 paket (aktif & nonaktif),
 * 4 voucher (persen, fixed, kedaluwarsa, terbatas), 1 undangan kedaluwarsa,
 * dan 6 user dengan berbagai role. Password semua user: password123
 */

use App\Models\Assessment;
use App\Models\Institution;
use App\Models\InstitutionInvite;
use App\Models\Module;
use App\Models\Package;
use App\Models\User;
use App\Models\Voucher;

$inst = Institution::create(['name' => 'Lembaga Uji', 'status' => 'active']);
$inst2 = Institution::create(['name' => 'Lembaga Lain', 'status' => 'active']);

$module = Module::create([
    'institution_id' => $inst->id,
    'title' => 'Modul Laravel',
    'is_active' => true,
    'xp_reward' => 30,
]);
$assessment = Assessment::create([
    'module_id' => $module->id,
    'type' => 'tugas',
    'title' => 'Tugas Pertama',
    'max_score' => 100,
    'xp_reward' => 50,
]);

$pkgActive = Package::create([
    'institution_id' => $inst->id,
    'title' => 'Paket Pemula', 'description' => 'Paket uji', 'price' => 200000, 'is_active' => true,
]);
$pkgActive->modules()->attach($module->id, ['sort_order' => 1]);

$pkgInactive = Package::create([
    'institution_id' => $inst->id,
    'title' => 'Paket Nonaktif', 'description' => 'Tidak tersedia', 'price' => 100000, 'is_active' => false,
]);

Voucher::create(['code' => 'DISC10',   'discount_type' => 'percent', 'discount_value' => 10,    'is_active' => true]);
Voucher::create(['code' => 'POTONG50', 'discount_type' => 'fixed',   'discount_value' => 50000, 'is_active' => true]);
Voucher::create(['code' => 'EXPIRED',  'discount_type' => 'percent', 'discount_value' => 10,    'is_active' => true, 'expires_at' => now()->subDay()]);
Voucher::create(['code' => 'LIMIT1',   'discount_type' => 'fixed',   'discount_value' => 10000, 'is_active' => true, 'max_uses' => 1, 'used_count' => 0]);

InstitutionInvite::create([
    'institution_id' => $inst->id,
    'email' => 'expired@test.com',
    'token' => 'EXPIREDTOKEN123',
    'role' => 'siswa',
    'expires_at' => now()->subDay(),
]);

$defs = [
    ['Mentor Uji',  'mentor@test.com',  'mentor',  $inst->id],
    ['Mentor Lain', 'mentor2@test.com', 'mentor',  $inst2->id],
    ['Admin Uji',   'admin@test.com',   'admin',   null],
    ['Siswa Uji',   'siswa@test.com',   'siswa',   null],
    ['Siswa Dua',   'siswa2@test.com',  'siswa',   null],
    ['Buyer Tiga',  'buyer@test.com',   'siswa',   null],
    ['Lembaga Uji', 'lembaga@test.com', 'lembaga', $inst->id],
];

foreach ($defs as [$name, $email, $role, $instId]) {
    $u = User::create([
        'name' => $name, 'email' => $email, 'password' => 'password123',
        'is_active' => true, 'institution_id' => $instId,
    ]);
    $u->assignRole($role);
}

echo "SETUP_OK institution={$inst->id} institution2={$inst2->id} module={$module->id} assessment={$assessment->id} package_active={$pkgActive->id} package_inactive={$pkgInactive->id}\n";
