<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MaterialCompleted;
use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentCompletion;
use App\Models\Curriculum;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumController extends Controller
{
    // ─── Kurikulum ─────────────────────────────────────────────────────────────

    /**
     * Daftar kurikulum (admin/mentor/lembaga melihat milik institusi sendiri).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $curricula = Curriculum::with('owner:id,name')
            ->when(
                ! $user->isPlatformAdmin(),
                fn ($q) => $q->where('institution_id', $user->institution_id)
            )
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15);

        return response()->json([
            'message' => 'Daftar kurikulum berhasil diambil.',
            'data' => $curricula,
        ]);
    }

    /**
     * Detail kurikulum + modul + konten.
     */
    public function show(Request $request, Curriculum $curriculum): JsonResponse
    {
        abort_unless(
            $request->user()->canAccessInstitution($curriculum->institution_id),
            403,
            'Kamu tidak memiliki izin untuk mengakses kurikulum ini.'
        );

        $curriculum->load('modules.contents');

        return response()->json([
            'message' => 'Detail kurikulum berhasil diambil.',
            'data' => $curriculum,
        ]);
    }

    /**
     * Buat kurikulum baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,published,archived'],
        ]);

        $curriculum = Curriculum::create([
            'owner_id' => $request->user()->id,
            'institution_id' => $request->user()->institution_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'draft',
        ]);

        return response()->json([
            'message' => 'Kurikulum berhasil dibuat.',
            'data' => $curriculum,
        ], 201);
    }

    /**
     * Update kurikulum.
     */
    public function update(Request $request, Curriculum $curriculum): JsonResponse
    {
        abort_unless(
            $request->user()->canAccessInstitution($curriculum->institution_id),
            403,
            'Kamu tidak memiliki izin untuk mengubah kurikulum ini.'
        );

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:draft,published,archived'],
        ]);

        $curriculum->update($validated);

        return response()->json([
            'message' => 'Kurikulum berhasil diperbarui.',
            'data' => $curriculum,
        ]);
    }

    /**
     * Hapus kurikulum.
     */
    public function destroy(Request $request, Curriculum $curriculum): JsonResponse
    {
        abort_unless(
            $request->user()->canAccessInstitution($curriculum->institution_id),
            403,
            'Kamu tidak memiliki izin untuk menghapus kurikulum ini.'
        );

        $curriculum->delete();

        return response()->json([
            'message' => 'Kurikulum berhasil dihapus.',
        ]);
    }

    // ─── Module (di dalam kurikulum) ────────────────────────────────────────────

    /**
     * Tambah modul ke dalam kurikulum.
     */
    public function storeModule(Request $request, Curriculum $curriculum): JsonResponse
    {
        abort_unless(
            $request->user()->canAccessInstitution($curriculum->institution_id),
            403,
            'Kamu tidak memiliki izin untuk menambah modul di kurikulum ini.'
        );

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'xp_reward' => ['nullable', 'integer', 'min:0'],
        ]);

        $maxOrder = $curriculum->modules()->max('sort_order') ?? 0;

        $module = $curriculum->modules()->create([
            'institution_id' => $curriculum->institution_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $maxOrder + 1,
            'xp_reward' => $validated['xp_reward'] ?? 0,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Modul berhasil ditambahkan ke kurikulum.',
            'data' => $module,
        ], 201);
    }

    // ─── Content (di dalam modul) ───────────────────────────────────────────────

    /**
     * Tambah konten (video/dokumen/link) ke dalam modul.
     */
    public function storeContent(Request $request, Module $module): JsonResponse
    {
        abort_unless(
            $request->user()->canAccessInstitution($module->institution_id),
            403,
            'Kamu tidak memiliki izin untuk menambah konten di modul ini.'
        );

        $validated = $request->validate([
            'type' => ['required', 'in:video,dokumen,link'],
            'title' => ['required', 'string', 'max:255'],
            'file_url' => ['nullable', 'string', 'max:2048'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'xp_reward' => ['nullable', 'integer', 'min:0'],
        ]);

        $maxOrder = $module->contents()->max('sort_order') ?? 0;

        $content = $module->contents()->create([
            'type' => $validated['type'],
            'title' => $validated['title'],
            'file_url' => $validated['file_url'] ?? null,
            'external_url' => $validated['external_url'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $maxOrder + 1,
            'xp_reward' => $validated['xp_reward'] ?? 0,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Konten berhasil ditambahkan ke modul.',
            'data' => $content,
        ], 201);
    }

    /**
     * Tambah tugas/ujian ke dalam modul (mentor/pengelola).
     */
    public function storeAssessment(Request $request, Module $module): JsonResponse
    {
        $user = $request->user();

        // Butuh privilege pengelola (bukan sekadar scope tenant — siswa satu
        // lembaga juga lolos canAccessInstitution) DAN akses lembaga modul.
        abort_unless(
            $user->isPlatformAdmin() || $user->hasAnyRole(['mentor', 'lembaga']),
            403,
            'Kamu tidak memiliki izin untuk menambah tugas di modul ini.'
        );

        abort_unless(
            $user->canAccessInstitution($module->institution_id),
            403,
            'Kamu tidak memiliki izin untuk menambah tugas di modul ini.'
        );

        $validated = $request->validate([
            'type' => ['required', 'in:tugas,ujian'],
            'title' => ['required', 'string', 'max:255'],
            'max_score' => ['nullable', 'numeric', 'min:1'],
            'passing_score' => ['nullable', 'numeric', 'min:0'],
            'xp_reward' => ['nullable', 'integer', 'min:0'],
            'due_at' => ['nullable', 'date'],
        ]);

        $assessment = $module->assessments()->create([
            'type' => $validated['type'],
            'title' => $validated['title'],
            'max_score' => $validated['max_score'] ?? 100,
            'passing_score' => $validated['passing_score'] ?? null,
            'xp_reward' => $validated['xp_reward'] ?? 0,
            'due_at' => $validated['due_at'] ?? null,
        ]);

        return response()->json([
            'message' => 'Tugas/ujian berhasil ditambahkan ke modul.',
            'data' => $assessment,
        ], 201);
    }

    /**
     * Tandai konten selesai dipelajari oleh siswa + beri XP (jika ada).
     */
    public function completeContent(Request $request, Content $content): JsonResponse
    {
        $user = $request->user();

        // Hanya siswa yang ter-enroll di paket yang berisi modul konten ini
        $module = $content->module;
        $hasAccess = $module->packages()
            ->whereHas('enrollments', fn ($q) => $q->where('user_id', $user->id))
            ->exists()
            // Atau konten/modul tanpa paket (gratis/global)
            || $module->packages()->count() === 0;

        abort_unless($hasAccess, 403, 'Kamu belum terdaftar di paket yang berisi materi ini.');

        // Simpan riwayat completion (unique user_id + content_id, anti duplikat)
        $completion = ContentCompletion::firstOrCreate([
            'user_id' => $user->id,
            'content_id' => $content->id,
        ], [
            'completed_at' => now(),
        ]);

        $isNew = $completion->wasRecentlyCreated;

        // Beri XP hanya saat pertama kali menyelesaikan konten
        if ($isNew && $content->xp_reward > 0) {
            event(new MaterialCompleted(
                user: $user,
                materialId: $content->id,
                xpReward: $content->xp_reward,
            ));
        }

        return response()->json([
            'message' => $isNew
                ? 'Konten berhasil ditandai selesai.'
                : 'Konten ini sudah pernah ditandai selesai.',
            'data' => [
                'content_id' => $content->id,
                'completed_at' => $completion->completed_at,
                'xp_reward' => $content->xp_reward,
                'is_new' => $isNew,
            ],
        ], $isNew ? 201 : 200);
    }
}
