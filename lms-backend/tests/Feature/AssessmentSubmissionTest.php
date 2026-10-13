<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Level;
use App\Models\Module;
use App\Models\Submission;
use App\Models\User;
use Tests\TestCase;

class AssessmentSubmissionTest extends TestCase
{
    private function makeAssessment(array $attrs = []): Assessment
    {
        $module = Module::create(['title' => 'Modul A', 'is_active' => true]);

        return Assessment::create(array_merge([
            'module_id' => $module->id,
            'type' => 'tugas',
            'title' => 'Tugas 1',
            'max_score' => 100,
            'xp_reward' => 50,
        ], $attrs));
    }

    private function makeSiswa(): User
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        return $user;
    }

    private function makeMentor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('mentor');

        return $user;
    }

    // ─── Submit ────────────────────────────────────────────────────────────────

    public function test_siswa_dapat_submit_jawaban_teks(): void
    {
        $user = $this->makeSiswa();
        $assessment = $this->makeAssessment();

        $res = $this->actingAs($user)->postJson(
            "/api/v1/assessments/{$assessment->id}/submit",
            ['answer_text' => 'Jawaban saya']
        );

        $res->assertStatus(201)
            ->assertJsonPath('data.answer_text', 'Jawaban saya');

        $this->assertDatabaseHas('submissions', [
            'assessment_id' => $assessment->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_submit_pertama_returns_201_submit_ulang_returns_200(): void
    {
        $user = $this->makeSiswa();
        $assessment = $this->makeAssessment();

        $this->actingAs($user)->postJson(
            "/api/v1/assessments/{$assessment->id}/submit",
            ['answer_text' => 'Jawaban awal']
        )->assertStatus(201);

        $this->actingAs($user)->postJson(
            "/api/v1/assessments/{$assessment->id}/submit",
            ['answer_text' => 'Jawaban diperbarui']
        )->assertStatus(200);
    }

    public function test_submit_kosong_ditolak(): void
    {
        $user = $this->makeSiswa();
        $assessment = $this->makeAssessment();

        $this->actingAs($user)->postJson(
            "/api/v1/assessments/{$assessment->id}/submit",
            ['answer_text' => '']
        )->assertStatus(422)
            ->assertJsonValidationErrors('answer_text');
    }

    public function test_submit_ditolak_jika_lewat_deadline(): void
    {
        $user = $this->makeSiswa();
        $assessment = $this->makeAssessment(['due_at' => now()->subDay()]);

        $this->actingAs($user)->postJson(
            "/api/v1/assessments/{$assessment->id}/submit",
            ['answer_text' => 'Terlambat']
        )->assertStatus(422)
            ->assertJsonPath('message', 'Tenggat pengumpulan sudah lewat.');
    }

    public function test_tidak_bisa_submit_ulang_setelah_dinilai(): void
    {
        $user = $this->makeSiswa();
        $assessment = $this->makeAssessment();

        $submission = Submission::create([
            'assessment_id' => $assessment->id,
            'user_id' => $user->id,
            'answer_text' => 'Jawaban',
            'submitted_at' => now(),
            'score' => 80,
        ]);

        $this->actingAs($user)->postJson(
            "/api/v1/assessments/{$assessment->id}/submit",
            ['answer_text' => 'Coba ubah']
        )->assertStatus(422);
    }

    // ─── Index (daftar submission) ──────────────────────────────────────────────

    public function test_mentor_dapat_lihat_daftar_submission(): void
    {
        $mentor = $this->makeMentor();
        $assessment = $this->makeAssessment();

        $this->actingAs($mentor)->getJson(
            "/api/v1/assessments/{$assessment->id}/submissions"
        )->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_siswa_tidak_bisa_lihat_daftar_submission(): void
    {
        $user = $this->makeSiswa();
        $assessment = $this->makeAssessment();

        $this->actingAs($user)->getJson(
            "/api/v1/assessments/{$assessment->id}/submissions"
        )->assertStatus(403);
    }

    // ─── Grade ─────────────────────────────────────────────────────────────────

    public function test_mentor_dapat_menilai_submission(): void
    {
        Level::create(['name' => 'Pemula', 'min_xp' => 0]);
        $mentor = $this->makeMentor();
        $siswa = $this->makeSiswa();
        $assessment = $this->makeAssessment(['xp_reward' => 50]);

        $submission = Submission::create([
            'assessment_id' => $assessment->id,
            'user_id' => $siswa->id,
            'answer_text' => 'Jawaban',
            'submitted_at' => now(),
        ]);

        $res = $this->actingAs($mentor)->patchJson(
            "/api/v1/submissions/{$submission->id}/grade",
            ['score' => 85, 'feedback' => 'Bagus!']
        );

        $res->assertOk()
            ->assertJsonPath('data.score', 85);

        $this->assertDatabaseHas('submissions', [
            'id' => $submission->id,
            'score' => 85,
            'feedback' => 'Bagus!',
        ]);
    }

    public function test_xp_diberikan_jika_nilai_melebihi_threshold(): void
    {
        Level::create(['name' => 'Pemula', 'min_xp' => 0]);
        $mentor = $this->makeMentor();
        $siswa = $this->makeSiswa();
        $assessment = $this->makeAssessment(['max_score' => 100, 'xp_reward' => 50]);

        $submission = Submission::create([
            'assessment_id' => $assessment->id,
            'user_id' => $siswa->id,
            'answer_text' => 'Jawaban',
            'submitted_at' => now(),
        ]);

        // Nilai 80 ≥ 70% dari 100 → dapat XP
        $this->actingAs($mentor)->patchJson(
            "/api/v1/submissions/{$submission->id}/grade",
            ['score' => 80]
        )->assertOk();

        $this->assertDatabaseHas('xp_logs', [
            'user_id' => $siswa->id,
            'source' => 'task_completion',
            'points' => 50,
        ]);
    }

    public function test_xp_tidak_diberikan_jika_nilai_di_bawah_threshold(): void
    {
        Level::create(['name' => 'Pemula', 'min_xp' => 0]);
        $mentor = $this->makeMentor();
        $siswa = $this->makeSiswa();
        $assessment = $this->makeAssessment(['max_score' => 100, 'xp_reward' => 50]);

        $submission = Submission::create([
            'assessment_id' => $assessment->id,
            'user_id' => $siswa->id,
            'answer_text' => 'Jawaban',
            'submitted_at' => now(),
        ]);

        // Nilai 60 < 70% dari 100 → tidak dapat XP
        $this->actingAs($mentor)->patchJson(
            "/api/v1/submissions/{$submission->id}/grade",
            ['score' => 60]
        )->assertOk();

        $this->assertDatabaseMissing('xp_logs', ['user_id' => $siswa->id]);
    }

    public function test_nilai_tidak_boleh_melebihi_max_score(): void
    {
        $mentor = $this->makeMentor();
        $siswa = $this->makeSiswa();
        $assessment = $this->makeAssessment(['max_score' => 100]);

        $submission = Submission::create([
            'assessment_id' => $assessment->id,
            'user_id' => $siswa->id,
            'answer_text' => 'Jawaban',
            'submitted_at' => now(),
        ]);

        $this->actingAs($mentor)->patchJson(
            "/api/v1/submissions/{$submission->id}/grade",
            ['score' => 150]
        )->assertStatus(422)
            ->assertJsonValidationErrors('score');
    }

    public function test_siswa_tidak_bisa_menilai(): void
    {
        $siswa = $this->makeSiswa();
        $assessment = $this->makeAssessment();

        $submission = Submission::create([
            'assessment_id' => $assessment->id,
            'user_id' => $siswa->id,
            'answer_text' => 'Jawaban',
            'submitted_at' => now(),
        ]);

        $this->actingAs($siswa)->patchJson(
            "/api/v1/submissions/{$submission->id}/grade",
            ['score' => 90]
        )->assertStatus(403);
    }

    public function test_passing_score_kustom_dipakai_sebagai_threshold(): void
    {
        Level::create(['name' => 'Pemula', 'min_xp' => 0]);
        $mentor = $this->makeMentor();
        $siswa = $this->makeSiswa();
        $assessment = $this->makeAssessment([
            'max_score' => 100,
            'xp_reward' => 50,
            'passing_score' => 90,
        ]);

        $submission = Submission::create([
            'assessment_id' => $assessment->id,
            'user_id' => $siswa->id,
            'answer_text' => 'Jawaban',
            'submitted_at' => now(),
        ]);

        // Nilai 80 < passing_score 90 → tidak dapat XP (walau > 70% default).
        $this->actingAs($mentor)->patchJson(
            "/api/v1/submissions/{$submission->id}/grade",
            ['score' => 80]
        )->assertOk();

        $this->assertDatabaseMissing('xp_logs', ['user_id' => $siswa->id]);
    }
}
