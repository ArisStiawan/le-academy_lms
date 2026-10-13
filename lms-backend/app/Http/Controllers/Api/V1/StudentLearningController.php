<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Content;
use App\Models\ContentCompletion;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tampilan pembelajaran sisi siswa: materi modul yang boleh diakses
 * dan daftar tugas/ujian beserta status pengumpulannya.
 */
class StudentLearningController extends Controller
{
    /**
     * Daftar materi (per modul) dari paket yang diikuti siswa.
     * Modul tanpa paket (gratis/global) ikut terbuka.
     */
    public function materials(Request $request): JsonResponse
    {
        $user = $request->user();
        $moduleIds = $this->accessibleModuleIds($user->id);

        $modules = Module::whereIn('id', $moduleIds)
            ->where('is_active', true)
            ->with([
                'curriculum:id,title',
                'contents' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('title')
            ->get();

        $contentIds = $modules->pluck('contents')->flatten()->pluck('id')->all();

        $completed = ContentCompletion::where('user_id', $user->id)
            ->whereIn('content_id', $contentIds)
            ->pluck('content_id')
            ->flip();

        $totalContents = 0;
        $totalCompleted = 0;
        $xpEarned = 0;

        $payload = $modules->map(function (Module $module) use ($completed, &$totalContents, &$totalCompleted, &$xpEarned) {
            $contents = $module->contents->map(function ($content) use ($completed, &$totalCompleted, &$xpEarned) {
                $isCompleted = $completed->has($content->id);

                if ($isCompleted) {
                    $totalCompleted++;
                    $xpEarned += (int) $content->xp_reward;
                }

                return [
                    'id' => $content->id,
                    'type' => $content->type,
                    'title' => $content->title,
                    'description' => $content->description,
                    'file_url' => $content->file_url,
                    'external_url' => $content->external_url,
                    'sort_order' => $content->sort_order,
                    'xp_reward' => (int) $content->xp_reward,
                    'is_completed' => $isCompleted,
                ];
            });

            $total = $contents->count();
            $done = $contents->where('is_completed', true)->count();
            $totalContents += $total;

            return [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'curriculum' => $module->curriculum?->title,
                'xp_reward' => (int) $module->xp_reward,
                'total' => $total,
                'completed' => $done,
                'progress_pct' => $total > 0 ? round($done / $total * 100, 1) : 0.0,
                'contents' => $contents->values(),
            ];
        });

        return response()->json([
            'message' => 'Daftar materi berhasil diambil.',
            'data' => [
                'summary' => [
                    'modules' => $payload->count(),
                    'contents' => $totalContents,
                    'completed' => $totalCompleted,
                    'xp_earned' => $xpEarned,
                ],
                'modules' => $payload->values(),
            ],
        ]);
    }

    /**
     * Daftar tugas & ujian dari paket yang diikuti siswa, lengkap
     * dengan status pengumpulan/penilaian.
     */
    public function assessments(Request $request): JsonResponse
    {
        $user = $request->user();
        $moduleIds = $this->accessibleModuleIds($user->id);

        $assessments = Assessment::whereIn('module_id', $moduleIds)
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->with([
                'module:id,title,curriculum_id',
                'module.curriculum:id,title',
                'submissions' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->orderBy('due_at')
            ->get();

        $rows = $assessments->map(function (Assessment $assessment) {
            $submission = $assessment->submissions->first();

            $isSubmitted = $submission !== null && $submission->submitted_at !== null;
            $isGraded = $submission !== null && $submission->score !== null;
            $isOverdue = $assessment->due_at !== null && $assessment->due_at->isPast();

            $status = match (true) {
                $isGraded => 'dinilai',
                $isSubmitted => 'terkumpul',
                $isOverdue => 'terlambat',
                default => 'belum',
            };

            return [
                'id' => $assessment->id,
                'type' => $assessment->type,
                'title' => $assessment->title,
                'module' => $assessment->module?->title,
                'curriculum' => $assessment->module?->curriculum?->title,
                'max_score' => (float) $assessment->max_score,
                'passing_score' => $assessment->passing_score !== null ? (float) $assessment->passing_score : null,
                'xp_reward' => (int) $assessment->xp_reward,
                'due_at' => $assessment->due_at,
                'status' => $status,
                'is_submitted' => $isSubmitted,
                'is_graded' => $isGraded,
                'is_overdue' => $isOverdue,
                'score' => $submission?->score,
                'feedback' => $submission?->feedback,
                'submitted_at' => $submission?->submitted_at,
            ];
        });

        return response()->json([
            'message' => 'Daftar tugas & ujian berhasil diambil.',
            'data' => [
                'summary' => [
                    'total' => $rows->count(),
                    'tugas' => $rows->where('type', 'tugas')->count(),
                    'ujian' => $rows->where('type', 'ujian')->count(),
                    'belum' => $rows->where('status', 'belum')->count(),
                    'terkumpul' => $rows->where('status', 'terkumpul')->count(),
                    'dinilai' => $rows->where('status', 'dinilai')->count(),
                    'terlambat' => $rows->where('status', 'terlambat')->count(),
                ],
                'assessments' => $rows->values(),
            ],
        ]);
    }

    /**
     * ID modul yang boleh diakses siswa: modul dari paket yang diikuti
     * + modul tanpa paket (gratis/global).
     *
     * @return array<int, int>
     */
    private function accessibleModuleIds(int $userId): array
    {
        $enrolled = Module::whereHas('packages.enrollments', fn ($q) => $q->where('user_id', $userId))
            ->pluck('id');

        $free = Module::whereDoesntHave('packages')->pluck('id');

        return $enrolled->merge($free)->unique()->values()->all();
    }

    /**
     * Detail satu materi: isi bacaan + info modul & progres, untuk
     * ditampilkan sebagai halaman baca di dalam aplikasi.
     */
    public function materialShow(Request $request, Content $content): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            in_array($content->module_id, $this->accessibleModuleIds($user->id), true),
            403,
            'Kamu tidak memiliki akses ke materi ini.'
        );

        $content->loadMissing('module:id,title,description,curriculum_id', 'module.curriculum:id,title');

        $moduleContentIds = Content::where('module_id', $content->module_id)
            ->where('is_active', true)
            ->pluck('id');

        $isCompleted = ContentCompletion::where('user_id', $user->id)
            ->where('content_id', $content->id)
            ->exists();

        $total = $moduleContentIds->count();
        $done = ContentCompletion::where('user_id', $user->id)
            ->whereIn('content_id', $moduleContentIds)
            ->count();

        return response()->json([
            'message' => 'Detail materi berhasil diambil.',
            'data' => [
                'id' => $content->id,
                'type' => $content->type,
                'title' => $content->title,
                'description' => $content->description,
                'body' => $content->body,
                'file_url' => $content->file_url,
                'external_url' => $content->external_url,
                'xp_reward' => (int) $content->xp_reward,
                'is_completed' => $isCompleted,
                'module' => [
                    'id' => $content->module?->id,
                    'title' => $content->module?->title,
                    'description' => $content->module?->description,
                    'curriculum' => $content->module?->curriculum?->title,
                ],
                'module_progress' => [
                    'total' => $total,
                    'completed' => $done,
                    'progress_pct' => $total > 0 ? round($done / $total * 100, 1) : 0.0,
                ],
            ],
        ]);
    }

    /**
     * Detail satu tugas/ujian: soal pilihan ganda + status pengumpulan.
     * Kunci jawaban sengaja TIDAK dikirim ke klien.
     */
    public function assessmentShow(Request $request, Assessment $assessment): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            in_array($assessment->module_id, $this->accessibleModuleIds($user->id), true),
            403,
            'Kamu tidak memiliki akses ke tugas ini.'
        );

        $assessment->loadMissing('module:id,title,curriculum_id', 'module.curriculum:id,title');
        $assessment->load('questions');

        $submission = $assessment->submissions()
            ->where('user_id', $user->id)
            ->first();

        $isSubmitted = $submission !== null && $submission->submitted_at !== null;
        $isGraded = $submission !== null && $submission->score !== null;

        return response()->json([
            'message' => 'Detail tugas berhasil diambil.',
            'data' => [
                'id' => $assessment->id,
                'type' => $assessment->type,
                'title' => $assessment->title,
                'module' => [
                    'id' => $assessment->module?->id,
                    'title' => $assessment->module?->title,
                    'curriculum' => $assessment->module?->curriculum?->title,
                ],
                'max_score' => (float) $assessment->max_score,
                'passing_score' => $assessment->passing_score !== null ? (float) $assessment->passing_score : null,
                'xp_reward' => (int) $assessment->xp_reward,
                'due_at' => $assessment->due_at,
                'is_submitted' => $isSubmitted,
                'is_graded' => $isGraded,
                'score' => $submission?->score,
                'feedback' => $submission?->feedback,
                'submitted_at' => $submission?->submitted_at,
                'questions' => $assessment->questions->map(fn ($question) => [
                    'id' => $question->id,
                    'question' => $question->question,
                    'options' => $question->options,
                ])->values(),
            ],
        ]);
    }
}
