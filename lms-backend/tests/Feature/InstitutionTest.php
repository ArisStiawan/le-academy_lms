<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\InstitutionInvite;
use App\Models\Module;
use App\Models\Package;
use App\Models\User;
use Tests\TestCase;

class InstitutionTest extends TestCase
{
    private function makePackageWithModule(): Package
    {
        $module = Module::create(['title' => 'Modul B2B', 'is_active' => true]);
        $package = Package::create(['title' => 'Paket Lembaga', 'price' => 0, 'is_active' => true]);
        $package->modules()->attach($module->id, ['sort_order' => 1]);

        return $package;
    }

    private function makeAdmin(?int $institutionId = null): User
    {
        $user = User::factory()->create(['institution_id' => $institutionId]);
        $user->assignRole('admin');

        return $user;
    }

    private function makeSiswa(): User
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        return $user;
    }

    // ─── Import ────────────────────────────────────────────────────────────────

    public function test_admin_dapat_import_siswa_baru(): void
    {
        $institution = Institution::create(['name' => 'SMK Maju']);
        $admin = $this->makeAdmin($institution->id);
        $package = $this->makePackageWithModule();

        $res = $this->actingAs($admin)->postJson('/api/v1/institution/students/import', [
            'package_id' => $package->id,
            'emails' => ['siswa1@test.com', 'siswa2@test.com'],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.summary.created_and_enrolled', 2);

        $this->assertDatabaseHas('users', ['email' => 'siswa1@test.com']);
        $this->assertDatabaseHas('users', ['email' => 'siswa2@test.com']);
        $this->assertDatabaseCount('enrollments', 2);
    }

    public function test_import_siswa_yang_sudah_ada_tidak_buat_akun_baru(): void
    {
        $institution = Institution::create(['name' => 'SMK Maju']);
        $admin = $this->makeAdmin($institution->id);
        $package = $this->makePackageWithModule();
        $existing = User::factory()->create(['email' => 'existing@test.com']);
        $existing->assignRole('siswa');

        $res = $this->actingAs($admin)->postJson('/api/v1/institution/students/import', [
            'package_id' => $package->id,
            'emails' => ['existing@test.com'],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.summary.created_and_enrolled', 0)
            ->assertJsonPath('data.summary.enrolled_existing', 1);

        $this->assertDatabaseCount('users', 2); // admin + existing
    }

    public function test_import_skip_siswa_yang_sudah_enrolled(): void
    {
        $institution = Institution::create(['name' => 'SMK Maju']);
        $admin = $this->makeAdmin($institution->id);
        $package = $this->makePackageWithModule();
        $siswa = $this->makeSiswa();

        Enrollment::create([
            'user_id' => $siswa->id,
            'package_id' => $package->id,
            'progress_pct' => 0,
            'enrolled_at' => now(),
        ]);

        $res = $this->actingAs($admin)->postJson('/api/v1/institution/students/import', [
            'package_id' => $package->id,
            'emails' => [$siswa->email],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.summary.skipped_already_enrolled', 1);

        $this->assertDatabaseCount('enrollments', 1); // tidak ada duplikasi
    }

    public function test_siswa_tidak_bisa_import(): void
    {
        $siswa = $this->makeSiswa();
        $package = $this->makePackageWithModule();

        $this->actingAs($siswa)->postJson('/api/v1/institution/students/import', [
            'package_id' => $package->id,
            'emails' => ['siapapun@test.com'],
        ])->assertStatus(403);
    }

    // ─── Lembaga Bimbingan (B2B) ───────────────────────────────────────────────

    private function makeLembaga(?int $institutionId = null): User
    {
        $user = User::factory()->create(['institution_id' => $institutionId]);
        $user->assignRole('lembaga');

        return $user;
    }

    public function test_lembaga_dapat_import_siswa_ke_paketnya(): void
    {
        $institution = Institution::create(['name' => 'Lembaga Bimbel']);
        $lembaga = $this->makeLembaga($institution->id);
        $package = Package::create([
            'institution_id' => $institution->id,
            'title' => 'Paket Lembaga',
            'price' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($lembaga)->postJson('/api/v1/institution/students/import', [
            'package_id' => $package->id,
            'emails' => ['siswa1@test.com', 'siswa2@test.com'],
        ])->assertStatus(201)
            ->assertJsonPath('data.summary.created_and_enrolled', 2);

        $this->assertDatabaseHas('users', ['email' => 'siswa1@test.com']);
        $this->assertDatabaseHas('enrollments', ['package_id' => $package->id]);
    }

    public function test_lembaga_tidak_bisa_import_ke_paket_lembaga_lain(): void
    {
        $instA = Institution::create(['name' => 'Lembaga A']);
        $instB = Institution::create(['name' => 'Lembaga B']);
        $lembagaA = $this->makeLembaga($instA->id);

        $packageB = Package::create([
            'institution_id' => $instB->id,
            'title' => 'Paket B',
            'price' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($lembagaA)->postJson('/api/v1/institution/students/import', [
            'package_id' => $packageB->id,
            'emails' => ['siswa@test.com'],
        ])->assertStatus(403);
    }

    public function test_lembaga_dapat_mengirim_undangan(): void
    {
        $institution = Institution::create(['name' => 'Lembaga Bimbel']);
        $lembaga = $this->makeLembaga($institution->id);

        $this->actingAs($lembaga)->postJson('/api/v1/institution/students/invite', [
            'emails' => ['calon@test.com'],
        ])->assertStatus(201);

        $this->assertDatabaseHas('institution_invites', [
            'institution_id' => $institution->id,
            'email' => 'calon@test.com',
        ]);
    }

    // ─── Invite ────────────────────────────────────────────────────────────────

    public function test_admin_dapat_membuat_undangan(): void
    {
        $institution = Institution::create(['name' => 'SMK Maju']);
        $admin = $this->makeAdmin($institution->id);
        $package = $this->makePackageWithModule();

        $res = $this->actingAs($admin)->postJson('/api/v1/institution/students/invite', [
            'emails' => ['calon@test.com'],
            'package_id' => $package->id,
        ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['data' => [['email', 'invite_url', 'expires_at']]]);

        $this->assertDatabaseHas('institution_invites', [
            'institution_id' => $institution->id,
            'email' => 'calon@test.com',
        ]);
    }

    public function test_invite_tanpa_institution_ditolak(): void
    {
        $admin = User::factory()->create(['institution_id' => null]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->postJson('/api/v1/institution/students/invite', [
            'emails' => ['calon@test.com'],
        ])->assertStatus(422);
    }

    // ─── Show & Accept Invite ──────────────────────────────────────────────────

    public function test_dapat_melihat_detail_undangan_via_token(): void
    {
        $institution = Institution::create(['name' => 'SMK Maju']);
        $invite = InstitutionInvite::create([
            'institution_id' => $institution->id,
            'email' => 'invited@test.com',
            'token' => 'test-token-123',
            'role' => 'siswa',
            'expires_at' => now()->addDays(7),
        ]);

        $this->getJson('/api/v1/institution/invites/test-token-123')
            ->assertOk()
            ->assertJsonPath('data.email', 'invited@test.com');
    }

    public function test_undangan_expired_ditolak(): void
    {
        $institution = Institution::create(['name' => 'SMK Maju']);
        InstitutionInvite::create([
            'institution_id' => $institution->id,
            'email' => 'invited@test.com',
            'token' => 'expired-token',
            'role' => 'siswa',
            'expires_at' => now()->subDay(), // sudah kedaluwarsa
        ]);

        $this->getJson('/api/v1/institution/invites/expired-token')
            ->assertStatus(410);
    }

    public function test_terima_undangan_buat_akun_baru_dan_enroll(): void
    {
        $institution = Institution::create(['name' => 'SMK Maju']);
        $package = $this->makePackageWithModule();

        InstitutionInvite::create([
            'institution_id' => $institution->id,
            'email' => 'newuser@test.com',
            'token' => 'valid-token-abc',
            'role' => 'siswa',
            'package_id' => $package->id,
            'expires_at' => now()->addDays(7),
        ]);

        $res = $this->postJson('/api/v1/institution/invites/valid-token-abc/accept', [
            'name' => 'User Baru',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', ['email' => 'newuser@test.com']);
        $this->assertDatabaseHas('enrollments', ['package_id' => $package->id]);
        $this->assertDatabaseHas('institution_invites', [
            'token' => 'valid-token-abc',
        ]);
        // accepted_at harus terisi
        $invite = InstitutionInvite::where('token', 'valid-token-abc')->first();
        $this->assertNotNull($invite->accepted_at);
    }

    public function test_undangan_yang_sudah_diterima_tidak_bisa_dipakai_lagi(): void
    {
        $institution = Institution::create(['name' => 'SMK Maju']);
        InstitutionInvite::create([
            'institution_id' => $institution->id,
            'email' => 'used@test.com',
            'token' => 'used-token',
            'role' => 'siswa',
            'accepted_at' => now(), // sudah digunakan
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson('/api/v1/institution/invites/used-token/accept', [
            'name' => 'Siapapun',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(410);
    }
}
