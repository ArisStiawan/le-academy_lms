<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    /**
     * Peringkat pengguna berdasarkan total XP (dari tabel users_level).
     */
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));

        $user = $request->user();

        $leaderboard = UserLevel::with('user:id,name')
            ->with('level:id,name,min_xp')
            ->orderByDesc('total_xp')
            ->limit($limit)
            ->get()
            ->map(function (UserLevel $entry, int $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $entry->user->only('id', 'name'),
                    'total_xp' => $entry->total_xp,
                    'level' => $entry->level?->name,
                ];
            });

        // Posisi user yang login (di luar daftar teratas)
        $myProgress = null;
        if ($user) {
            $myLevel = UserLevel::with('level:id,name,min_xp')
                ->where('user_id', $user->id)
                ->first();

            if ($myLevel) {
                $myRank = UserLevel::where('total_xp', '>', $myLevel->total_xp)->count() + 1;

                $myProgress = [
                    'rank' => $myRank,
                    'total_xp' => $myLevel->total_xp,
                    'level' => $myLevel->level?->name,
                    'min_xp' => $myLevel->level?->min_xp,
                    'next_level' => Level::where('min_xp', '>', $myLevel->total_xp)
                        ->orderBy('min_xp')
                        ->first()?->name,
                    'xp_to_next_level' => Level::where('min_xp', '>', $myLevel->total_xp)
                        ->orderBy('min_xp')
                        ->first()?->min_xp - $myLevel->total_xp,
                ];
            }
        }

        return response()->json([
            'message' => 'Leaderboard berhasil diambil.',
            'data' => [
                'leaderboard' => $leaderboard,
                'my_progress' => $myProgress,
            ],
        ]);
    }
}
