<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\Institution;
use App\Models\User;
use Tests\TestCase;

class ForumTest extends TestCase
{
    private function makeInstitution(): Institution
    {
        return Institution::create(['name' => 'Lembaga '.uniqid()]);
    }

    private function makeUser(string $role = 'siswa', ?Institution $institution = null): User
    {
        $user = User::factory()->create(['institution_id' => $institution?->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCurriculum(User $owner): Curriculum
    {
        return Curriculum::create([
            'owner_id' => $owner->id,
            'institution_id' => $owner->institution_id,
            'title' => 'Kurikulum Forum',
            'status' => 'published',
        ]);
    }

    public function test_siswa_dapat_membuat_thread_di_kurikulum(): void
    {
        $mentor = $this->makeUser('mentor', $this->makeInstitution());
        $curriculum = $this->makeCurriculum($mentor);
        $siswa = $this->makeUser('siswa', $mentor->institution);

        $res = $this->actingAs($siswa)->postJson('/api/v1/forum/threads', [
            'curriculum_id' => $curriculum->id,
            'title' => 'Pertanyaan tentang modul 1',
            'body' => 'Materinya sudah saya baca, tapi ada yang kurang jelas.',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.title', 'Pertanyaan tentang modul 1');

        $this->assertDatabaseHas('forum_threads', [
            'title' => 'Pertanyaan tentang modul 1',
            'user_id' => $siswa->id,
        ]);
    }

    public function test_user_lembaga_lain_tidak_bisa_membuat_thread(): void
    {
        $instA = $this->makeInstitution();
        $owner = $this->makeUser('mentor', $instA);
        $curriculum = $this->makeCurriculum($owner);

        $instB = $this->makeInstitution();
        $intruder = $this->makeUser('siswa', $instB);

        $this->actingAs($intruder)->postJson('/api/v1/forum/threads', [
            'curriculum_id' => $curriculum->id,
            'title' => 'Nyolong akses',
            'body' => 'haha',
        ])->assertStatus(403);
    }

    public function test_thread_dan_balasan_bisa_dibaca(): void
    {
        $mentor = $this->makeUser('mentor', $this->makeInstitution());
        $curriculum = $this->makeCurriculum($mentor);
        $siswa = $this->makeUser('siswa', $mentor->institution);

        $thread = ForumThread::create([
            'curriculum_id' => $curriculum->id,
            'user_id' => $siswa->id,
            'title' => 'Diskusi Bab 2',
            'body' => 'Ada yang mau diskusi?',
        ]);

        ForumPost::create([
            'thread_id' => $thread->id,
            'user_id' => $mentor->id,
            'body' => 'Silakan tanya saja.',
        ]);

        // Daftar thread
        $this->actingAs($siswa)->getJson("/api/v1/forum/threads?curriculum_id={$curriculum->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.posts_count', 1);

        // Detail thread + balasan
        $this->actingAs($siswa)->getJson("/api/v1/forum/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonPath('data.posts_count', 1)
            ->assertJsonCount(1, 'data.posts');
    }

    public function test_mentor_dapat_menutup_thread(): void
    {
        $mentor = $this->makeUser('mentor', $this->makeInstitution());
        $curriculum = $this->makeCurriculum($mentor);
        $siswa = $this->makeUser('siswa', $mentor->institution);

        $thread = ForumThread::create([
            'curriculum_id' => $curriculum->id,
            'user_id' => $siswa->id,
            'title' => 'Diskusi Bab 3',
            'body' => 'halo',
        ]);

        $this->actingAs($mentor)->patchJson("/api/v1/forum/threads/{$thread->id}/toggle-close")
            ->assertOk()
            ->assertJsonPath('data.is_closed', true);

        $this->assertDatabaseHas('forum_threads', [
            'id' => $thread->id,
            'is_closed' => true,
        ]);
    }

    public function test_siswa_lain_tidak_bisa_menutup_thread(): void
    {
        $mentor = $this->makeUser('mentor', $this->makeInstitution());
        $curriculum = $this->makeCurriculum($mentor);
        $siswa = $this->makeUser('siswa', $mentor->institution);
        $other = $this->makeUser('siswa', $mentor->institution);

        $thread = ForumThread::create([
            'curriculum_id' => $curriculum->id,
            'user_id' => $siswa->id,
            'title' => 'Diskusi Bab 4',
            'body' => 'halo',
        ]);

        $this->actingAs($other)->patchJson("/api/v1/forum/threads/{$thread->id}/toggle-close")
            ->assertStatus(403);
    }

    public function test_pemilik_thread_bisa_menghapus_thread(): void
    {
        $mentor = $this->makeUser('mentor', $this->makeInstitution());
        $curriculum = $this->makeCurriculum($mentor);
        $siswa = $this->makeUser('siswa', $mentor->institution);

        $thread = ForumThread::create([
            'curriculum_id' => $curriculum->id,
            'user_id' => $siswa->id,
            'title' => 'Akan dihapus',
            'body' => 'hapus aku',
        ]);

        $this->actingAs($siswa)->deleteJson("/api/v1/forum/threads/{$thread->id}")
            ->assertOk();

        $this->assertDatabaseMissing('forum_threads', ['id' => $thread->id]);
    }
}
