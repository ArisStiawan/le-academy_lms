<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Content;
use App\Models\ContentCompletion;
use App\Models\Curriculum;
use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\Module;
use App\Models\Package;
use App\Models\Schedule;
use App\Models\Submission;
use App\Models\User;
use Tests\TestCase;

class StudentLearningTest extends TestCase
{
    // ─── Helper ────────────────────────────────────────────────────────────────

    private function makeInstitution(): Institution
    {
        return Institution::create(['name' => 'Lembaga '.uniqid()]);
    }

    private function makeUser(string $role, ?Institution $institution = null): User
    {
        $user = User::factory()->create(['institution_id' => $institution?->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makeModule(?Institution $institution = null): Module
    {
        $owner = $this->makeUser('mentor', $institution);

        $curriculum = Curriculum::create([
            'owner_id' => $owner->id,
            'institution_id' => $institution?->id,
            'title' => 'Kurikulum '.uniqid(),
            'status' => 'published',
        ]);

        return Module::create([
            'curriculum_id' => $curriculum->id,
            'institution_id' => $institution?->id,
            'title' => 'Modul Logika '.uniqid(),
            'is_active' => true,
            'xp_reward' => 10,
        ]);
    }

    private function makeContent(Module $module, string $title = 'Video Pengantar'): Content
    {
        return Content::create([
            'module_id' => $module->id,
            'type' => 'video',
            'title' => $title,
            'external_url' => 'https://example.com/video',
            'xp_reward' => 15,
            'is_active' => true,
        ]);
    }

    private function makeAssessment(Module $module, array $attrs = []): Assessment
    {
        return $module->assessments()->create(array_merge([
            'type' => 'tugas',
            'title' => 'Tugas Logika',
            'max_score' => 100,
            'xp_reward' => 25,
        ], $attrs));
    }

    private function enroll(User $user, Module $module): Package
    {
        $package = Package::create([
            'title' => 'Paket '.uniqid(),
            'price' => 100000,
            'is_active' => true,
        ]);
        $package->modules()->attach($module->id, ['sort_order' => 1]);

        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'progress_pct' => 0,
            'enrolled_at' => now(),
        ]);

        return $package;
    }

    private function makeBimbingan(?Institution $institution = null, array $attrs = []): Schedule
    {
        $mentor = $this->makeUser('mentor', $institution);

        return Schedule::create(array_merge([
            'institution_id' => $institution?->id,
            'mentor_id' => $mentor->id,
            'title' => 'Bimbingan Online',
            'type' => 'bimbingan',
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHour(),
            'zoom_url' => 'https://zoom.us/j/123456',
            'is_active' => true,
        ], $attrs));
    }

    // ─── Materi ────────────────────────────────────────────────────────────────

    public function test_siswa_melihat_materi_dari_paket_yang_diikuti(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $this->makeContent($module, 'Video A');
        $this->makeContent($module, 'Video B');
        $this->enroll($siswa, $module);

        $this->actingAs($siswa)->getJson('/api/v1/my/materials')
            ->assertOk()
            ->assertJsonPath('data.summary.modules', 1)
            ->assertJsonPath('data.summary.contents', 2)
            ->assertJsonPath('data.summary.completed', 0)
            ->assertJsonPath('data.modules.0.title', $module->title)
            ->assertJsonCount(2, 'data.modules.0.contents')
            ->assertJsonPath('data.modules.0.contents.0.is_completed', false);
    }

    public function test_materi_yang_sudah_selesai_ditandai_completed(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $content = $this->makeContent($module);
        $this->enroll($siswa, $module);

        ContentCompletion::create([
            'user_id' => $siswa->id,
            'content_id' => $content->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($siswa)->getJson('/api/v1/my/materials')
            ->assertOk()
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.modules.0.progress_pct', 100)
            ->assertJsonPath('data.modules.0.contents.0.is_completed', true);
    }

    public function test_siswa_tidak_melihat_materi_paket_yang_tidak_diikuti(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $this->makeContent($module);

        // Modul terikat paket, tetapi siswa ini tidak ter-enroll.
        $this->enroll($this->makeUser('siswa', $inst), $module);

        $this->actingAs($siswa)->getJson('/api/v1/my/materials')
            ->assertOk()
            ->assertJsonCount(0, 'data.modules');
    }

    public function test_modul_tanpa_paket_terbuka_untuk_semua_siswa(): void
    {
        $siswa = $this->makeUser('siswa');
        $module = $this->makeModule();
        $this->makeContent($module);

        $this->actingAs($siswa)->getJson('/api/v1/my/materials')
            ->assertOk()
            ->assertJsonCount(1, 'data.modules')
            ->assertJsonPath('data.modules.0.title', $module->title);
    }

    // ─── Tugas & Ujian ─────────────────────────────────────────────────────────

    public function test_siswa_melihat_tugas_dan_ujian_paket_yang_diikuti(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $this->makeAssessment($module, ['type' => 'tugas', 'title' => 'Tugas Pertama']);
        $this->makeAssessment($module, ['type' => 'ujian', 'title' => 'Ujian Tengah']);
        $this->enroll($siswa, $module);

        $this->actingAs($siswa)->getJson('/api/v1/my/assessments')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.tugas', 1)
            ->assertJsonPath('data.summary.ujian', 1)
            ->assertJsonPath('data.summary.belum', 2);
    }

    public function test_daftar_tugas_bisa_difilter_per_tipe(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $this->makeAssessment($module, ['type' => 'tugas', 'title' => 'Tugas A']);
        $this->makeAssessment($module, ['type' => 'ujian', 'title' => 'Ujian A']);
        $this->enroll($siswa, $module);

        $this->actingAs($siswa)->getJson('/api/v1/my/assessments?type=ujian')
            ->assertOk()
            ->assertJsonCount(1, 'data.assessments')
            ->assertJsonPath('data.assessments.0.type', 'ujian')
            ->assertJsonPath('data.assessments.0.title', 'Ujian A');
    }

    public function test_siswa_melihat_status_pengumpulan_dan_penilaian(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $tugas = $this->makeAssessment($module, ['type' => 'tugas']);
        $this->enroll($siswa, $module);

        Submission::create([
            'assessment_id' => $tugas->id,
            'user_id' => $siswa->id,
            'answer_text' => 'Jawaban saya',
            'submitted_at' => now(),
        ]);

        $this->actingAs($siswa)->getJson('/api/v1/my/assessments?type=tugas')
            ->assertOk()
            ->assertJsonPath('data.assessments.0.status', 'terkumpul')
            ->assertJsonPath('data.assessments.0.is_submitted', true);

        // Setelah mentor menilai.
        Submission::where('assessment_id', $tugas->id)
            ->update(['score' => 88, 'feedback' => 'Kerja bagus']);

        $this->actingAs($siswa)->getJson('/api/v1/my/assessments?type=tugas')
            ->assertOk()
            ->assertJsonPath('data.assessments.0.status', 'dinilai')
            ->assertJsonPath('data.assessments.0.is_graded', true)
            ->assertJsonPath('data.assessments.0.feedback', 'Kerja bagus');
    }

    public function test_siswa_tidak_melihat_tugas_modul_lain(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $this->makeAssessment($module, ['title' => 'Tugas Rahasia']);
        $this->enroll($this->makeUser('siswa', $inst), $module);

        $this->actingAs($siswa)->getJson('/api/v1/my/assessments')
            ->assertOk()
            ->assertJsonCount(0, 'data.assessments');
    }

    // ─── Pembuatan tugas/ujian oleh mentor ─────────────────────────────────────

    public function test_mentor_dapat_membuat_tugas_ujian(): void
    {
        $inst = $this->makeInstitution();
        $mentor = $this->makeUser('mentor', $inst);
        $module = $this->makeModule($inst);

        $this->actingAs($mentor)->postJson(
            "/api/v1/modules/{$module->id}/assessments",
            [
                'type' => 'ujian',
                'title' => 'Ujian Akhir',
                'max_score' => 100,
                'passing_score' => 70,
                'xp_reward' => 50,
                'due_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            ]
        )->assertStatus(201)
            ->assertJsonPath('data.title', 'Ujian Akhir')
            ->assertJsonPath('data.type', 'ujian');

        $this->assertDatabaseHas('assessments', [
            'module_id' => $module->id,
            'title' => 'Ujian Akhir',
            'type' => 'ujian',
        ]);
    }

    public function test_tipe_assessment_harus_tugas_atau_ujian(): void
    {
        $inst = $this->makeInstitution();
        $mentor = $this->makeUser('mentor', $inst);
        $module = $this->makeModule($inst);

        $this->actingAs($mentor)->postJson(
            "/api/v1/modules/{$module->id}/assessments",
            ['type' => 'kuis', 'title' => 'Kuis']
        )->assertStatus(422);
    }

    public function test_siswa_tidak_dapat_membuat_tugas(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);

        $this->actingAs($siswa)->postJson(
            "/api/v1/modules/{$module->id}/assessments",
            ['type' => 'tugas', 'title' => 'Tugas Palsu']
        )->assertStatus(403);
    }

    public function test_mentor_lembaga_lain_tidak_dapat_membuat_tugas(): void
    {
        $instA = $this->makeInstitution();
        $module = $this->makeModule($instA);
        $intruder = $this->makeUser('mentor', $this->makeInstitution());

        $this->actingAs($intruder)->postJson(
            "/api/v1/modules/{$module->id}/assessments",
            ['type' => 'tugas', 'title' => 'Tugas Intruder']
        )->assertStatus(403);
    }

    // ─── Jadwal bimbingan daring ──────────────────────────────────────────────

    public function test_daftar_bimbingan_menampilkan_status_pendaftaran(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $bimbingan = $this->makeBimbingan($inst);

        $this->actingAs($siswa)->getJson('/api/v1/schedules?type=bimbingan')
            ->assertOk()
            ->assertJsonPath('data.data.0.type', 'bimbingan')
            ->assertJsonPath('data.data.0.is_registered', false);

        $this->actingAs($siswa)->postJson("/api/v1/schedules/{$bimbingan->id}/register")
            ->assertStatus(201);

        $this->actingAs($siswa)->getJson('/api/v1/schedules?type=bimbingan')
            ->assertOk()
            ->assertJsonPath('data.data.0.is_registered', true);
    }

    public function test_jadwal_saya_bisa_difilter_per_tipe(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $bimbingan = $this->makeBimbingan($inst);
        $webinar = $this->makeBimbingan($inst, ['type' => 'webinar', 'title' => 'Webinar X']);

        $siswa->schedules()->attach($bimbingan->id);
        $siswa->schedules()->attach($webinar->id);

        $this->actingAs($siswa)->getJson('/api/v1/my/schedules?type=bimbingan')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.type', 'bimbingan');
    }

    // ─── Detail materi (halaman baca) ─────────────────────────────────────────

    private function makeQuestion(Assessment $assessment, array $attrs = []): AssessmentQuestion
    {
        return $assessment->questions()->create(array_merge([
            'question' => 'Berapa konstanta dari 3x + 5?',
            'options' => ['3', '5', 'x', '3x'],
            'correct_index' => 1,
            'sort_order' => 1,
        ], $attrs));
    }

    public function test_siswa_membuka_detail_materi_dan_melihat_isi_bacaan(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $content = Content::create([
            'module_id' => $module->id,
            'type' => 'dokumen',
            'title' => 'Mengenal Bentuk Aljabar',
            'body' => "## 1. Mengenal Bentuk Aljabar\n\nAljabar memakai huruf untuk mewakili angka.\n\n- Variabel: huruf pengganti nilai.",
            'xp_reward' => 10,
            'is_active' => true,
        ]);
        $this->enroll($siswa, $module);

        $this->actingAs($siswa)->getJson("/api/v1/my/materials/{$content->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $content->id)
            ->assertJsonPath('data.title', 'Mengenal Bentuk Aljabar')
            ->assertJsonPath('data.body', fn ($v) => str_contains($v, 'Mengenal Bentuk Aljabar'))
            ->assertJsonPath('data.module.title', $module->title)
            ->assertJsonPath('data.is_completed', false)
            ->assertJsonPath('data.module_progress.total', 1);
    }

    public function test_detail_materi_menandai_konten_yang_sudah_selesai(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $content = $this->makeContent($module);
        $this->enroll($siswa, $module);

        ContentCompletion::create([
            'user_id' => $siswa->id,
            'content_id' => $content->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($siswa)->getJson("/api/v1/my/materials/{$content->id}")
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);
    }

    public function test_detail_materi_menolak_konten_di_luar_paket_siswa(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $content = $this->makeContent($module);

        // Terikat paket, tapi bukan siswa ini yang ter-enroll.
        $this->enroll($this->makeUser('siswa', $inst), $module);

        $this->actingAs($siswa)->getJson("/api/v1/my/materials/{$content->id}")
            ->assertStatus(403);
    }

    // ─── Detail tugas/ujian (halaman soal) ────────────────────────────────────

    public function test_siswa_membuka_detail_tugas_dan_melihat_soal_pilihan_ganda(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $tugas = $this->makeAssessment($module, ['type' => 'tugas', 'title' => 'Tugas Aljabar']);
        $this->makeQuestion($tugas);
        $this->enroll($siswa, $module);

        $this->actingAs($siswa)->getJson("/api/v1/my/assessments/{$tugas->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tugas->id)
            ->assertJsonPath('data.title', 'Tugas Aljabar')
            ->assertJsonPath('data.module.title', $module->title)
            ->assertJsonCount(1, 'data.questions')
            ->assertJsonPath('data.questions.0.question', 'Berapa konstanta dari 3x + 5?')
            ->assertJsonPath('data.questions.0.options.1', '5')
            // Kunci jawaban TIDAK boleh bocor ke klien.
            ->assertJsonMissingPath('data.questions.0.correct_index');
    }

    public function test_detail_tugas_menolak_assessment_di_luar_paket(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $tugas = $this->makeAssessment($module);

        $this->enroll($this->makeUser('siswa', $inst), $module);

        $this->actingAs($siswa)->getJson("/api/v1/my/assessments/{$tugas->id}")
            ->assertStatus(403);
    }

    // ─── Menjawab kuis (auto-nilai) ───────────────────────────────────────────

    public function test_menjawab_kuis_langsung_dinilai_otomatis(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $tugas = $this->makeAssessment($module, ['max_score' => 100]);
        $q1 = $this->makeQuestion($tugas, ['options' => ['3', '5', 'x'], 'correct_index' => 1, 'sort_order' => 1]);
        $q2 = $this->makeQuestion($tugas, ['question' => 'Koefisien dari 7x?', 'options' => ['7', 'x', '7x'], 'correct_index' => 0, 'sort_order' => 2]);
        $this->enroll($siswa, $module);

        $this->actingAs($siswa)->postJson("/api/v1/assessments/{$tugas->id}/submit", [
            'answers' => [
                (string) $q1->id => 1,  // benar
                (string) $q2->id => 2,  // salah
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.score', 50)
            ->assertJsonPath('data.correct_count', 1)
            ->assertJsonPath('data.total_questions', 2);

        $this->assertDatabaseHas('submissions', [
            'assessment_id' => $tugas->id,
            'user_id' => $siswa->id,
        ]);

        // Status di daftar berubah jadi 'dinilai'.
        $this->actingAs($siswa)->getJson('/api/v1/my/assessments')
            ->assertOk()
            ->assertJsonPath('data.assessments.0.status', 'dinilai')
            ->assertJsonPath('data.assessments.0.is_graded', true);
    }

    public function test_jawaban_kuis_tanpa_pilihan_ditolak(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $tugas = $this->makeAssessment($module);
        $this->makeQuestion($tugas);
        $this->enroll($siswa, $module);

        $this->actingAs($siswa)->postJson("/api/v1/assessments/{$tugas->id}/submit", [])
            ->assertStatus(422);
    }

    public function test_kuis_dapat_dijawab_ulang_untuk_memperbaiki_nilai(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $module = $this->makeModule($inst);
        $tugas = $this->makeAssessment($module, ['max_score' => 100]);
        $q1 = $this->makeQuestion($tugas, ['options' => ['3', '5', 'x'], 'correct_index' => 1]);
        $this->enroll($siswa, $module);

        // Jawaban salah → 0.
        $this->actingAs($siswa)->postJson("/api/v1/assessments/{$tugas->id}/submit", [
            'answers' => [(string) $q1->id => 0],
        ])->assertStatus(201)->assertJsonPath('data.score', 0);

        // Jawaban benar → nilai diperbarui.
        $this->actingAs($siswa)->postJson("/api/v1/assessments/{$tugas->id}/submit", [
            'answers' => [(string) $q1->id => 1],
        ])->assertOk()->assertJsonPath('data.score', 100);
    }
}
