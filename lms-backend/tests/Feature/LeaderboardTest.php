<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\User;
use App\Models\UserLevel;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    public function test_leaderboard_mengurutkan_user_berdasarkan_total_xp(): void
    {
        Level::create(['name' => 'Pemula', 'min_xp' => 0]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userA->assignRole('siswa');
        $userB->assignRole('siswa');

        UserLevel::create(['user_id' => $userA->id, 'level_id' => 1, 'total_xp' => 100]);
        UserLevel::create(['user_id' => $userB->id, 'level_id' => 1, 'total_xp' => 250]);

        $res = $this->actingAs($userA)->getJson('/api/v1/gamification/leaderboard');

        $res->assertOk()
            ->assertJsonPath('data.leaderboard.0.user.id', $userB->id)
            ->assertJsonPath('data.leaderboard.1.user.id', $userA->id);
    }

    public function test_leaderboard_menampilkan_progress_user_yang_login(): void
    {
        Level::create(['name' => 'Pemula', 'min_xp' => 0]);
        Level::create(['name' => 'Menengah', 'min_xp' => 100]);

        $user = User::factory()->create();
        $user->assignRole('siswa');
        $other = User::factory()->create();
        $other->assignRole('siswa');

        $pemula = Level::where('name', 'Pemula')->first();
        $menengah = Level::where('name', 'Menengah')->first();
        UserLevel::create(['user_id' => $user->id, 'level_id' => $menengah->id, 'total_xp' => 150]);
        UserLevel::create(['user_id' => $other->id, 'level_id' => $pemula->id, 'total_xp' => 500]);

        $res = $this->actingAs($user)->getJson('/api/v1/gamification/leaderboard');

        $res->assertOk()
            ->assertJsonPath('data.my_progress.rank', 2)
            ->assertJsonPath('data.my_progress.level', 'Menengah');
    }
}
