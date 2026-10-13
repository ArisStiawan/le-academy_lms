<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\Package;
use App\Models\User;
use Tests\TestCase;

class EnrollmentUserTest extends TestCase
{
    private function makeInstitution(): Institution
    {
        return Institution::create(['name' => 'Lembaga '.uniqid()]);
    }

    private function makeSiswa(?Institution $institution = null): User
    {
        $user = User::factory()->create(['institution_id' => $institution?->id]);
        $user->assignRole('siswa');

        return $user;
    }

    private function makePackage(?Institution $institution = null): Package
    {
        return Package::create([
            'institution_id' => $institution?->id,
            'title' => 'Paket '.uniqid(),
            'price' => 150000,
            'is_active' => true,
        ]);
    }

    public function test_siswa_melihat_daftar_enrollment_miliknya(): void
    {
        $siswa = $this->makeSiswa();
        $lain = $this->makeSiswa();
        $package = $this->makePackage();

        Enrollment::create([
            'user_id' => $siswa->id,
            'package_id' => $package->id,
            'progress_pct' => 40,
            'enrolled_at' => now(),
        ]);
        Enrollment::create([
            'user_id' => $lain->id,
            'package_id' => $package->id,
            'progress_pct' => 10,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($siswa)->getJson('/api/v1/my/enrollments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.progress_pct', 40);
    }

    public function test_admin_melihat_semua_pengguna(): void
    {
        $admin = User::factory()->create(['institution_id' => null]);
        $admin->assignRole('admin');

        $this->makeSiswa();
        $this->makeSiswa();

        $this->actingAs($admin)->getJson('/api/v1/users')
            ->assertOk();
    }

    public function test_mentor_hanya_melihat_pengguna_lembaganya(): void
    {
        $instA = $this->makeInstitution();
        $instB = $this->makeInstitution();

        $mentor = User::factory()->create(['institution_id' => $instA->id]);
        $mentor->assignRole('mentor');

        $this->makeSiswa($instA);
        $this->makeSiswa($instB);

        $res = $this->actingAs($mentor)->getJson('/api/v1/users')->assertOk();

        // mentor + 1 siswa di lembaga A = 2; siswa lembaga B tidak termasuk
        $ids = collect($res->json('data.data'))->pluck('institution_id')->unique()->values();
        $this->assertTrue($ids->every(fn ($id) => $id === $instA->id));
    }

    public function test_siswa_tidak_boleh_melihat_daftar_pengguna(): void
    {
        $siswa = $this->makeSiswa();

        $this->actingAs($siswa)->getJson('/api/v1/users')
            ->assertStatus(403);
    }
}
