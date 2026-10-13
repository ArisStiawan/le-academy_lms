<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\Level;
use App\Models\Module;
use App\Models\Package;
use App\Models\Submission;
use App\Models\User;
use Tests\TestCase;

class TenantScopingTest extends TestCase
{
    private function institution(string $name): Institution
    {
        return Institution::create(['name' => $name, 'status' => 'active']);
    }

    private function userWithRole(string $role, ?int $institutionId = null): User
    {
        $user = User::factory()->create(['institution_id' => $institutionId]);
        $user->assignRole($role);

        return $user;
    }

    private function assessmentOwnedBy(?int $institutionId): Assessment
    {
        $module = Module::create([
            'institution_id' => $institutionId,
            'title' => 'Modul',
            'is_active' => true,
        ]);

        return Assessment::create([
            'module_id' => $module->id,
            'type' => 'tugas',
            'title' => 'Tugas',
            'max_score' => 100,
            'xp_reward' => 50,
        ]);
    }

    // ─── Submission scoping ────────────────────────────────────────────────────

    public function test_mentor_hanya_bisa_lihat_submission_lembaganya(): void
    {
        $instA = $this->institution('Lembaga A');
        $instB = $this->institution('Lembaga B');

        $assessment = $this->assessmentOwnedBy($instA->id);
        $mentorA = $this->userWithRole('mentor', $instA->id);
        $mentorB = $this->userWithRole('mentor', $instB->id);

        $this->actingAs($mentorA)
            ->getJson("/api/v1/assessments/{$assessment->id}/submissions")
            ->assertOk();

        $this->actingAs($mentorB)
            ->getJson("/api/v1/assessments/{$assessment->id}/submissions")
            ->assertStatus(403);
    }

    public function test_platform_admin_bisa_lihat_submission_lembaga_manapun(): void
    {
        $instA = $this->institution('Lembaga A');
        $assessment = $this->assessmentOwnedBy($instA->id);
        $admin = $this->userWithRole('admin'); // tanpa institution

        $this->actingAs($admin)
            ->getJson("/api/v1/assessments/{$assessment->id}/submissions")
            ->assertOk();
    }

    public function test_mentor_tidak_bisa_menilai_submission_lembaga_lain(): void
    {
        $instA = $this->institution('Lembaga A');
        $instB = $this->institution('Lembaga B');
        Level::create(['name' => 'Pemula', 'min_xp' => 0]);

        $assessment = $this->assessmentOwnedBy($instA->id);
        $siswa = $this->userWithRole('siswa');
        $mentorB = $this->userWithRole('mentor', $instB->id);

        $submission = Submission::create([
            'assessment_id' => $assessment->id,
            'user_id' => $siswa->id,
            'answer_text' => 'Jawaban',
            'submitted_at' => now(),
        ]);

        $this->actingAs($mentorB)->patchJson(
            "/api/v1/submissions/{$submission->id}/grade",
            ['score' => 80]
        )->assertStatus(403);
    }

    // ─── Enrollment requirement ────────────────────────────────────────────────

    public function test_siswa_harus_enrolled_untuk_submit_modul_berpaket(): void
    {
        $inst = $this->institution('Lembaga A');
        $module = Module::create([
            'institution_id' => $inst->id,
            'title' => 'Modul',
            'is_active' => true,
        ]);
        $package = Package::create(['title' => 'Paket', 'price' => 0, 'is_active' => true]);
        $package->modules()->attach($module->id, ['sort_order' => 1]);

        $assessment = Assessment::create([
            'module_id' => $module->id,
            'type' => 'tugas',
            'title' => 'Tugas',
            'max_score' => 100,
            'xp_reward' => 50,
        ]);

        $siswa = $this->userWithRole('siswa');

        // Belum enroll → ditolak
        $this->actingAs($siswa)->postJson(
            "/api/v1/assessments/{$assessment->id}/submit",
            ['answer_text' => 'Jawaban']
        )->assertStatus(403);

        // Setelah enroll → boleh
        Enrollment::create([
            'user_id' => $siswa->id,
            'package_id' => $package->id,
            'progress_pct' => 0,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($siswa)->postJson(
            "/api/v1/assessments/{$assessment->id}/submit",
            ['answer_text' => 'Jawaban']
        )->assertStatus(201);
    }

    public function test_modul_tanpa_paket_tetap_bisa_di_submit(): void
    {
        // Modul tidak terikat paket apa pun → tidak ada konsep enrollment.
        $assessment = $this->assessmentOwnedBy(null);
        $siswa = $this->userWithRole('siswa');

        $this->actingAs($siswa)->postJson(
            "/api/v1/assessments/{$assessment->id}/submit",
            ['answer_text' => 'Jawaban']
        )->assertStatus(201);
    }

    // ─── Certificate scoping ───────────────────────────────────────────────────

    public function test_certificate_index_dan_approve_terbatas_lembaga(): void
    {
        $instA = $this->institution('Lembaga A');
        $instB = $this->institution('Lembaga B');

        $package = Package::create([
            'institution_id' => $instA->id,
            'title' => 'Paket A',
            'price' => 0,
            'is_active' => true,
        ]);

        $siswa = $this->userWithRole('siswa');
        $enrollment = Enrollment::create([
            'user_id' => $siswa->id,
            'package_id' => $package->id,
            'progress_pct' => 100,
            'enrolled_at' => now(),
        ]);

        $certificate = Certificate::create([
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'pending',
        ]);

        $mentorA = $this->userWithRole('mentor', $instA->id);
        $mentorB = $this->userWithRole('mentor', $instB->id);

        // Index: mentor A melihat 1, mentor B melihat 0
        $this->actingAs($mentorA)->getJson('/api/v1/certificates')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->actingAs($mentorB)->getJson('/api/v1/certificates')
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        // Approve lintas lembaga ditolak
        $this->actingAs($mentorB)->patchJson(
            "/api/v1/certificates/{$certificate->id}/approve"
        )->assertStatus(403);

        // Approve oleh pemilik berhasil
        $this->actingAs($mentorA)->patchJson(
            "/api/v1/certificates/{$certificate->id}/approve"
        )->assertOk();
    }

    public function test_lembaga_melihat_dan_menyetujui_sertifikat_lembaganya(): void
    {
        $instA = $this->institution('Lembaga A');
        $instB = $this->institution('Lembaga B');

        $packageA = Package::create([
            'institution_id' => $instA->id,
            'title' => 'Paket A',
            'price' => 0,
            'is_active' => true,
        ]);

        $siswa = $this->userWithRole('siswa');
        $enrollment = Enrollment::create([
            'user_id' => $siswa->id,
            'package_id' => $packageA->id,
            'progress_pct' => 100,
            'enrolled_at' => now(),
        ]);

        $certificate = Certificate::create([
            'user_id' => $siswa->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'pending',
        ]);

        $lembagaA = $this->userWithRole('lembaga', $instA->id);
        $lembagaB = $this->userWithRole('lembaga', $instB->id);

        // Lembaga boleh mengakses daftar sertifikat (scoped)
        $this->actingAs($lembagaA)->getJson('/api/v1/certificates')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->actingAs($lembagaB)->getJson('/api/v1/certificates')
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        // Lintas lembaga ditolak
        $this->actingAs($lembagaB)->patchJson(
            "/api/v1/certificates/{$certificate->id}/approve"
        )->assertStatus(403);

        // Pemilik menyetujui
        $this->actingAs($lembagaA)->patchJson(
            "/api/v1/certificates/{$certificate->id}/approve"
        )->assertOk();
    }
}
