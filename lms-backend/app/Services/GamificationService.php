<?php

namespace App\Services;

use App\Models\Level;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GamificationService
{
    public function awardXp(
        User $user,
        int $points,
        string $source,
        int $referenceId
    ): UserLevel {
        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => 'XP harus lebih besar dari nol.',
            ]);
        }

        return DB::transaction(function () use (
            $user, $points, $source, $referenceId
        ) {
            // Pastikan hanya satu transaksi XP untuk sumber yang sama.
            $inserted = DB::table('xp_logs')->insertOrIgnore([
                'user_id' => $user->id,
                'points' => $points,
                'source' => $source,
                'reference_id' => $referenceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Jika sumber ini sudah pernah memberi XP, jangan tambah lagi.
            if ($inserted === 0) {
                return UserLevel::where('user_id', $user->id)
                    ->firstOrFail();
            }

            // Kunci baris progres user agar update XP tidak saling menimpa.
            $progress = UserLevel::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $progress) {
                $firstLevel = Level::orderBy('min_xp')->first();

                if (! $firstLevel) {
                    throw new \RuntimeException(
                        'Belum ada level yang terdaftar.'
                    );
                }

                $progress = UserLevel::create([
                    'user_id' => $user->id,
                    'level_id' => $firstLevel->id,
                    'total_xp' => 0,
                ]);
            }

            $progress->total_xp += $points;

            // Ambil level tertinggi yang persyaratan XP-nya terpenuhi.
            $level = Level::where(
                'min_xp',
                '<=',
                $progress->total_xp
            )
                ->orderByDesc('min_xp')
                ->first();

            if ($level) {
                $progress->level_id = $level->id;
            }

            $progress->save();

            return $progress->load('level');
        });
    }
}
