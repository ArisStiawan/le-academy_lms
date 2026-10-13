<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Schedule;
use App\Models\User;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    private function makeInstitution(): Institution
    {
        return Institution::create(['name' => 'Lembaga '.uniqid()]);
    }

    private function makeMentor(?Institution $institution = null): User
    {
        $inst = $institution ?? $this->makeInstitution();
        $user = User::factory()->create(['institution_id' => $inst->id]);
        $user->assignRole('mentor');

        return $user;
    }

    private function makeSiswa(?Institution $institution = null): User
    {
        $user = User::factory()->create(['institution_id' => $institution?->id]);
        $user->assignRole('siswa');

        return $user;
    }

    private function makeSchedule(array $attrs = []): Schedule
    {
        return Schedule::create(array_merge([
            'institution_id' => $this->makeInstitution()->id,
            'mentor_id' => $this->makeMentor()->id,
            'title' => 'Webinar Karir',
            'type' => 'webinar',
            'start_at' => now()->addDays(2),
            'is_active' => true,
        ], $attrs));
    }

    public function test_daftar_jadwal_publik_menampilkan_jadwal_aktif(): void
    {
        $this->makeSchedule();
        $this->makeSchedule(['is_active' => false]);

        $this->getJson('/api/v1/schedules')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_mentor_dapat_membuat_jadwal(): void
    {
        $mentor = $this->makeMentor();
        $institution = $mentor->institution;

        $res = $this->actingAs($mentor)->postJson('/api/v1/schedules', [
            'title' => 'Bimbingan Tugas Akhir',
            'type' => 'bimbingan',
            'start_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(1)->addHour()->format('Y-m-d H:i:s'),
            'zoom_url' => 'https://zoom.us/j/12345',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.title', 'Bimbingan Tugas Akhir');

        $this->assertDatabaseHas('schedules', [
            'title' => 'Bimbingan Tugas Akhir',
            'institution_id' => $institution->id,
        ]);
    }

    public function test_siswa_tidak_dapat_membuat_jadwal(): void
    {
        $siswa = $this->makeSiswa();

        $this->actingAs($siswa)->postJson('/api/v1/schedules', [
            'title' => 'Jadwal Palsu',
            'type' => 'webinar',
            'start_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
        ])->assertStatus(403);
    }

    public function test_siswa_dapat_mendaftar_ke_webinar(): void
    {
        $schedule = $this->makeSchedule();
        $siswa = $this->makeSiswa($schedule->institution);

        $this->actingAs($siswa)->postJson("/api/v1/schedules/{$schedule->id}/register")
            ->assertStatus(201)
            ->assertJsonPath('data.is_registered', true);

        $this->assertDatabaseHas('schedule_registrations', [
            'schedule_id' => $schedule->id,
            'user_id' => $siswa->id,
        ]);
    }

    public function test_siswa_tidak_bisa_mendaftar_dua_kali(): void
    {
        $schedule = $this->makeSchedule();
        $siswa = $this->makeSiswa($schedule->institution);

        $this->actingAs($siswa)->postJson("/api/v1/schedules/{$schedule->id}/register")
            ->assertStatus(201);

        $this->actingAs($siswa)->postJson("/api/v1/schedules/{$schedule->id}/register")
            ->assertStatus(200);

        $this->assertDatabaseCount('schedule_registrations', 1);
    }

    public function test_tidak_bisa_mendaftar_setelah_jadwal_selesai(): void
    {
        $schedule = $this->makeSchedule(['start_at' => now()->subDay()]);
        $siswa = $this->makeSiswa($schedule->institution);

        $this->actingAs($siswa)->postJson("/api/v1/schedules/{$schedule->id}/register")
            ->assertStatus(422);
    }

    public function test_mentor_dapat_menghapus_jadwal(): void
    {
        $institution = $this->makeInstitution();
        $mentor = $this->makeMentor($institution);
        $schedule = Schedule::create([
            'institution_id' => $institution->id,
            'mentor_id' => $mentor->id,
            'title' => 'Webinar X',
            'type' => 'webinar',
            'start_at' => now()->addDays(3),
            'is_active' => true,
        ]);

        $this->actingAs($mentor)->deleteJson("/api/v1/schedules/{$schedule->id}")
            ->assertOk();

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_mentor_lembaga_lain_tidak_bisa_menghapus_jadwal(): void
    {
        $institution = $this->makeInstitution();
        $owner = $this->makeMentor($institution);
        $schedule = Schedule::create([
            'institution_id' => $institution->id,
            'mentor_id' => $owner->id,
            'title' => 'Webinar Rahasia',
            'type' => 'webinar',
            'start_at' => now()->addDays(3),
            'is_active' => true,
        ]);

        $intruder = $this->makeMentor();
        $this->actingAs($intruder)->deleteJson("/api/v1/schedules/{$schedule->id}")
            ->assertStatus(403);
    }
}
