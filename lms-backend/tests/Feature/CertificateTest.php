<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Package;
use App\Models\User;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    private function makePackage(): Package
    {
        $module = Module::create(['title' => 'Modul Sertifikat', 'is_active' => true]);
        $package = Package::create(['title' => 'Paket Sertifikat', 'price' => 0, 'is_active' => true]);
        $package->modules()->attach($module->id, ['sort_order' => 1]);

        return $package;
    }

    private function makeEnrollment(User $user, Package $package, float $pct = 100): Enrollment
    {
        return Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'progress_pct' => $pct,
            'enrolled_at' => now(),
        ]);
    }

    private function makeSiswa(): User
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        return $user;
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    // ─── Request ───────────────────────────────────────────────────────────────

    public function test_siswa_dapat_ajukan_sertifikat_jika_progress_100(): void
    {
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 100);

        $this->actingAs($siswa)->postJson('/api/v1/certificates/request', [
            'enrollment_id' => $enrollment->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('certificates', [
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'pending',
        ]);
    }

    public function test_ajukan_sertifikat_ditolak_jika_progress_belum_100(): void
    {
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 75);

        $this->actingAs($siswa)->postJson('/api/v1/certificates/request', [
            'enrollment_id' => $enrollment->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('enrollment_id');
    }

    public function test_tidak_bisa_ajukan_sertifikat_enrollment_orang_lain(): void
    {
        $siswa1 = $this->makeSiswa();
        $siswa2 = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa1, $package, 100);

        // siswa2 coba pakai enrollment_id milik siswa1
        $this->actingAs($siswa2)->postJson('/api/v1/certificates/request', [
            'enrollment_id' => $enrollment->id,
        ])->assertStatus(404); // firstOrFail gagal karena user_id tidak cocok
    }

    public function test_tidak_bisa_ajukan_sertifikat_duplikat(): void
    {
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 100);

        $this->actingAs($siswa)->postJson('/api/v1/certificates/request', [
            'enrollment_id' => $enrollment->id,
        ])->assertStatus(201);

        $this->actingAs($siswa)->postJson('/api/v1/certificates/request', [
            'enrollment_id' => $enrollment->id,
        ])->assertStatus(409);
    }

    // ─── Approve ───────────────────────────────────────────────────────────────

    public function test_admin_dapat_menyetujui_sertifikat(): void
    {
        $admin = $this->makeAdmin();
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 100);

        $cert = Certificate::create([
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'pending',
        ]);

        $res = $this->actingAs($admin)->patchJson("/api/v1/certificates/{$cert->id}/approve");

        $res->assertOk()
            ->assertJsonPath('data.certificate_code', fn ($code) => str_starts_with($code, 'CERT-'));

        $this->assertDatabaseHas('certificates', [
            'id' => $cert->id,
            'status' => 'approved',
        ]);

        // Kode sertifikat harus terisi
        $this->assertNotNull(Certificate::find($cert->id)->certificate_code);
    }

    public function test_siswa_tidak_bisa_menyetujui_sertifikat(): void
    {
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 100);

        $cert = Certificate::create([
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'pending',
        ]);

        $this->actingAs($siswa)->patchJson("/api/v1/certificates/{$cert->id}/approve")
            ->assertStatus(403);
    }

    public function test_tidak_bisa_approve_sertifikat_yang_sudah_diproses(): void
    {
        $admin = $this->makeAdmin();
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 100);

        $cert = Certificate::create([
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'approved',
            'certificate_code' => 'CERT-EXISTING-2026',
            'issued_at' => now(),
        ]);

        $this->actingAs($admin)->patchJson("/api/v1/certificates/{$cert->id}/approve")
            ->assertStatus(422);
    }

    // ─── Reject ────────────────────────────────────────────────────────────────

    public function test_admin_dapat_menolak_sertifikat_dengan_catatan(): void
    {
        $admin = $this->makeAdmin();
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 100);

        $cert = Certificate::create([
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->patchJson("/api/v1/certificates/{$cert->id}/reject", [
            'notes' => 'Data tidak lengkap.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.notes', 'Data tidak lengkap.');

        $this->assertDatabaseHas('certificates', [
            'id' => $cert->id,
            'status' => 'rejected',
            'notes' => 'Data tidak lengkap.',
        ]);
    }

    // ─── Mine ──────────────────────────────────────────────────────────────────

    public function test_siswa_dapat_lihat_sertifikat_sendiri(): void
    {
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 100);

        Certificate::create([
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'approved',
            'certificate_code' => 'CERT-ABCDEFGH-2026',
            'issued_at' => now(),
        ]);

        $this->actingAs($siswa)->getJson('/api/v1/certificates/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ─── Index (admin) ─────────────────────────────────────────────────────────

    public function test_admin_dapat_lihat_semua_sertifikat(): void
    {
        $admin = $this->makeAdmin();
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 100);

        Certificate::create([
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->getJson('/api/v1/certificates')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_admin_dapat_filter_sertifikat_by_status(): void
    {
        $admin = $this->makeAdmin();
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 100);

        Certificate::create([
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->getJson('/api/v1/certificates?status=approved')
            ->assertOk()
            ->assertJsonPath('data.total', 0); // belum ada yang approved
    }

    // ─── Verify (Public) ───────────────────────────────────────────────────────

    public function test_verifikasi_kode_sertifikat_valid(): void
    {
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 100);

        Certificate::create([
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'approved',
            'certificate_code' => 'CERT-VALIDCODE-2026',
            'issued_at' => now(),
        ]);

        $this->getJson('/api/v1/certificates/verify/CERT-VALIDCODE-2026')
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonStructure(['data' => ['certificate_code', 'holder', 'package', 'issued_at']]);
    }

    public function test_verifikasi_kode_sertifikat_tidak_valid(): void
    {
        $this->getJson('/api/v1/certificates/verify/CERT-TIDAKADA-0000')
            ->assertStatus(404)
            ->assertJsonPath('valid', false);
    }

    public function test_verifikasi_sertifikat_pending_tidak_valid(): void
    {
        $siswa = $this->makeSiswa();
        $package = $this->makePackage();
        $enrollment = $this->makeEnrollment($siswa, $package, 100);

        // Sertifikat dengan kode tapi masih pending (edge case)
        Certificate::create([
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'pending',
            'certificate_code' => null,
        ]);

        $this->getJson('/api/v1/certificates/verify/CERT-APAPUN-2026')
            ->assertStatus(404)
            ->assertJsonPath('valid', false);
    }
}
