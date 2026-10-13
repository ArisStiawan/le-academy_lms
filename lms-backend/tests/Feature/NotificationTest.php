<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    private function makeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        return $user;
    }

    public function test_user_dapat_melihat_notifikasi_sendiri(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        Notification::create([
            'user_id' => $user->id,
            'type' => 'info',
            'title' => 'Sertifikat disetujui',
            'message' => 'Selamat!',
        ]);
        Notification::create([
            'user_id' => $other->id,
            'type' => 'info',
            'title' => 'Bukan milikku',
        ]);

        $res = $this->actingAs($user)->getJson('/api/v1/notifications');

        $res->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Sertifikat disetujui');
    }

    public function test_user_dapat_menandai_notifikasi_dibaca(): void
    {
        $user = $this->makeUser();

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'warning',
            'title' => 'Tugas mendekati tenggat',
            'is_read' => false,
        ]);

        $this->actingAs($user)->patchJson(
            "/api/v1/notifications/{$notification->id}/read"
        )->assertOk();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_user_tidak_bisa_menandai_notifikasi_orang_lain(): void
    {
        $owner = $this->makeUser();
        $intruder = $this->makeUser();

        $notification = Notification::create([
            'user_id' => $owner->id,
            'type' => 'info',
            'title' => 'Rahasia',
            'is_read' => false,
        ]);

        $this->actingAs($intruder)->patchJson(
            "/api/v1/notifications/{$notification->id}/read"
        )->assertStatus(403);
    }

    public function test_mark_all_read_menandai_semua_notifikasi_user(): void
    {
        $user = $this->makeUser();

        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'N1', 'is_read' => false]);
        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'N2', 'is_read' => false]);

        $this->actingAs($user)->patchJson('/api/v1/notifications/read-all')->assertOk();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'is_read' => false,
        ]);
    }

    public function test_user_dapat_menghapus_notifikasi_sendiri(): void
    {
        $user = $this->makeUser();

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'info',
            'title' => 'Sampah',
        ]);

        $this->actingAs($user)->deleteJson(
            "/api/v1/notifications/{$notification->id}"
        )->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }
}
