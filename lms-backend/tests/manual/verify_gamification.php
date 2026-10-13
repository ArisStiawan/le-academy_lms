<?php

/**
 * Verifikasi gamifikasi (dijalankan setelah penilaian submission dengan skor 85).
 * Jalankan:  php artisan tinker tests/manual/verify_gamification.php
 *
 * Output yang diharapkan:
 *   XP_TASK=50            (XP dari penilaian tugas)
 *   TOTAL_AFTER_TASK=50
 *   MATLOG=30             (XP event MaterialCompleted = module.xp_reward)
 *   IDEMPOTENT_AFTER=180  (XP kedua dari sumber sama tidak menambah)
 *   LEVELUP=Mahir         (naik level otomatis)
 */

use App\Events\MaterialCompleted;
use App\Models\Module;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\XpLog;
use App\Services\GamificationService;

$user = User::where('email', 'siswa@test.com')->first();

$task = XpLog::where('user_id', $user->id)->where('source', 'task_completion')->sum('points');
echo "XP_TASK={$task}\n";
echo 'TOTAL_AFTER_TASK='.UserLevel::where('user_id', $user->id)->first()->total_xp."\n";

$module = Module::first();
event(new MaterialCompleted(user: $user, materialId: 555, xpReward: $module->xp_reward));
$mat = XpLog::where('user_id', $user->id)->where('source', 'material_completion')->where('reference_id', 555)->sum('points');
echo "MATLOG={$mat}\n";

$svc = app(GamificationService::class);
$svc->awardXp($user, 100, 'test_idem', 1);
$svc->awardXp($user, 100, 'test_idem', 1);
echo 'IDEMPOTENT_AFTER='.UserLevel::where('user_id', $user->id)->first()->total_xp."\n";

$svc->awardXp($user, 400, 'test_level', 2);
$ul = UserLevel::where('user_id', $user->id)->first()->load('level');
echo 'LEVELUP='.$ul->level->name."\n";
