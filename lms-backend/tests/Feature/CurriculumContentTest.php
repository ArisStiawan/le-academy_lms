<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Curriculum;
use App\Models\Institution;
use App\Models\Level;
use App\Models\Module;
use App\Models\Package;
use App\Models\User;
use Tests\TestCase;

class CurriculumContentTest extends TestCase
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

    private function makeSiswa(): User
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        return $user;
    }

    private function makeCurriculum(User $owner): Curriculum
    {
        return Curriculum::create([
            'owner_id' => $owner->id,
            'institution_id' => $owner->institution_id,
            'title' => 'Kurikulum Sains',
            'status' => 'published',
        ]);
    }

    private function makeModule(Curriculum $curriculum): Module
    {
        return Module::create([
            'curriculum_id' => $curriculum->id,
            'institution_id' => $curriculum->institution_id,
            'title' => 'Modul Fisika',
            'is_active' => true,
            'xp_reward' => 10,
        ]);
    }

    private function makeContent(Module $module): Content
    {
        return Content::create([
            'module_id' => $module->id,
            'type' => 'video',
            'title' => 'Video Pengantar',
            'file_url' => 'https://example.com/video.mp4',
            'xp_reward' => 20,
            'is_active' => true,
        ]);
    }

    private function makePaidPackage(Module $module): Package
    {
        $package = Package::create([
            'title' => 'Paket Premium',
            'price' => 100000,
            'is_active' => true,
        ]);
        $package->modules()->attach($module->id, ['sort_order' => 1]);

        return $package;
    }

    // ─── Kurikulum CRUD ─────────────────────────────────────────────────────────

    public function test_mentor_dapat_membuat_kurikulum(): void
    {
        $user = $this->makeMentor();

        $res = $this->actingAs($user)->postJson('/api/v1/curricula', [
            'title' => 'Kurikulum Baru',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.title', 'Kurikulum Baru')
            ->assertJsonPath('data.owner_id', $user->id);

        $this->assertDatabaseHas('curricula', [
            'title' => 'Kurikulum Baru',
            'owner_id' => $user->id,
        ]);
    }

    public function test_mentor_tidak_bisa_lihat_kurikulum_lembaga_lain(): void
    {
        $instA = $this->makeInstitution();
        $owner = $this->makeMentor($instA);
        $curriculum = $this->makeCurriculum($owner);

        $instB = $this->makeInstitution();
        $intruder = $this->makeMentor($instB);
        $this->actingAs($intruder)
            ->getJson("/api/v1/curricula/{$curriculum->id}")
            ->assertStatus(403);
    }

    // ─── Module & Content ───────────────────────────────────────────────────────

    public function test_tambah_modul_dan_konten_ke_kurikulum(): void
    {
        $user = $this->makeMentor();
        $curriculum = $this->makeCurriculum($user);

        $this->actingAs($user)->postJson(
            "/api/v1/curricula/{$curriculum->id}/modules",
            ['title' => 'Modul A', 'xp_reward' => 15]
        )->assertStatus(201);

        $module = Module::where('curriculum_id', $curriculum->id)->first();
        $this->assertNotNull($module);

        $this->actingAs($user)->postJson(
            "/api/v1/modules/{$module->id}/contents",
            [
                'type' => 'video',
                'title' => 'Video 1',
                'file_url' => 'https://example.com/v1.mp4',
                'xp_reward' => 20,
            ]
        )->assertStatus(201);

        $this->assertDatabaseHas('contents', [
            'module_id' => $module->id,
            'title' => 'Video 1',
        ]);
    }

    // ─── Completion & XP ────────────────────────────────────────────────────────

    public function test_siswa_menyelesaikan_konten_mendapat_xp(): void
    {
        Level::create(['name' => 'Pemula', 'min_xp' => 0]);

        $user = $this->makeSiswa();
        $mentor = $this->makeMentor();
        $curriculum = $this->makeCurriculum($mentor);
        $module = $this->makeModule($curriculum);
        $content = $this->makeContent($module);
        $this->makePaidPackage($module);

        // Enroll user ke paket
        $package = $module->packages()->first();
        $package->enrollments()->create([
            'user_id' => $user->id,
            'progress_pct' => 0,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($user)->postJson(
            "/api/v1/contents/{$content->id}/complete"
        )->assertStatus(201);

        $this->assertDatabaseHas('content_completions', [
            'user_id' => $user->id,
            'content_id' => $content->id,
        ]);

        $this->assertDatabaseHas('users_level', [
            'user_id' => $user->id,
            'total_xp' => 20,
        ]);
    }

    public function test_menyelesaikan_konten_yang_sama_tidak_mendapat_xp_ganda(): void
    {
        Level::create(['name' => 'Pemula', 'min_xp' => 0]);

        $user = $this->makeSiswa();
        $mentor = $this->makeMentor();
        $curriculum = $this->makeCurriculum($mentor);
        $module = $this->makeModule($curriculum);
        $content = $this->makeContent($module);
        $package = $this->makePaidPackage($module);
        $package->enrollments()->create([
            'user_id' => $user->id,
            'progress_pct' => 0,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($user)->postJson("/api/v1/contents/{$content->id}/complete")->assertStatus(201);
        $this->actingAs($user)->postJson("/api/v1/contents/{$content->id}/complete")->assertStatus(200);

        $this->assertDatabaseCount('content_completions', 1);

        $this->assertDatabaseHas('users_level', [
            'user_id' => $user->id,
            'total_xp' => 20,
        ]);
    }

    public function test_siswa_tanpa_enrollment_tidak_bisa_menyelesaikan_konten_berpaket(): void
    {
        $user = $this->makeSiswa();
        $mentor = $this->makeMentor();
        $curriculum = $this->makeCurriculum($mentor);
        $module = $this->makeModule($curriculum);
        $content = $this->makeContent($module);
        $this->makePaidPackage($module);

        $this->actingAs($user)->postJson(
            "/api/v1/contents/{$content->id}/complete"
        )->assertStatus(403);
    }
}
