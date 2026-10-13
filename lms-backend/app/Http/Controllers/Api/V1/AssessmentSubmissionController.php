<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\TaskGraded;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AssessmentSubmissionController extends Controller
{
    public function submit(
        Request $request,
        Assessment $assessment
    ): JsonResponse {
        // Validasi jawaban dan file yang dikirim peserta.
        $validated = $request->validate([
            'answer_text' => ['nullable', 'string', 'max:20000'],
            'file' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png,zip',
                'max:10240',
            ],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $assessment->loadMissing('questions');

        $hasQuiz = $assessment->questions->isNotEmpty();
        $hasAnswers = ! empty($validated['answers'] ?? []);
        $hasText = ! empty(trim($validated['answer_text'] ?? ''));
        $hasFile = $request->hasFile('file');

        if (! $hasAnswers && ! $hasText && ! $hasFile) {
            throw ValidationException::withMessages([
                'answer_text' => [
                    'Isi jawaban atau unggah file terlebih dahulu.',
                ],
            ]);
        }

        // Tolak pengumpulan jika tenggat sudah lewat.
        if ($assessment->due_at && now()->greaterThan($assessment->due_at)) {
            return response()->json([
                'message' => 'Tenggat pengumpulan sudah lewat.',
            ], 422);
        }

        $user = $request->user();

        // Konten yang terikat paket hanya boleh dikumpulkan oleh peserta yang
        // sudah ter-enroll di salah satu paket yang memuat modul ini.
        if (
            $this->isModulePackaged($assessment->module_id)
            && ! $this->userEnrolledInModule($user, $assessment->module_id)
        ) {
            return response()->json([
                'message' => 'Anda belum terdaftar pada paket yang memuat tugas ini.',
            ], 403);
        }

        // Satu peserta hanya memiliki satu submission per assessment.
        $submission = Submission::firstOrNew([
            'assessment_id' => $assessment->id,
            'user_id' => $user->id,
        ]);

        $isNew = ! $submission->exists;

        // Kuis pilihan ganda (auto-nilai) boleh dijawab ulang untuk memperbaiki
        // nilai; tugas manual yang sudah dinilai mentor tidak boleh diubah.
        if (! $isNew && $submission->score !== null && ! $hasQuiz) {
            return response()->json([
                'message' => 'Tugas ini sudah dinilai dan tidak dapat diubah.',
            ], 422);
        }

        // Nilai kuis dihitung otomatis dari kunci jawaban.
        $autoScore = null;
        $autoFeedback = null;
        $correctCount = null;
        $totalQuestions = null;

        if ($hasQuiz && $hasAnswers) {
            $totalQuestions = $assessment->questions->count();
            $correctCount = 0;

            foreach ($assessment->questions as $question) {
                $picked = $validated['answers'][(string) $question->id]
                    ?? $validated['answers'][$question->id]
                    ?? null;

                if ($picked !== null && (int) $picked === (int) $question->correct_index) {
                    $correctCount++;
                }
            }

            $maxScore = (float) $assessment->max_score;
            $autoScore = $totalQuestions > 0
                ? round($correctCount / $totalQuestions * $maxScore, 2)
                : 0.0;
            $autoFeedback = "Menjawab benar {$correctCount} dari {$totalQuestions} soal.";
        }

        // Upload file di luar transaksi untuk menghindari lock I/O yang panjang.
        $newFilePath = null;
        if ($request->hasFile('file')) {
            $newFilePath = $request->file('file')
                ->store('submissions', 'local');
        }

        $oldFilePath = $submission->file_url;

        try {
            DB::transaction(function () use (
                $submission, $validated, $newFilePath, $autoScore, $autoFeedback
            ) {
                $submission->answer_text = $validated['answer_text'] ?? null;
                $submission->submitted_at = now();

                if ($newFilePath !== null) {
                    $submission->file_url = $newFilePath;
                }

                // Kuis: nilai & umpan balik langsung disimpan.
                if ($autoScore !== null) {
                    $submission->score = $autoScore;
                    $submission->feedback = $autoFeedback;
                }

                $submission->save();
            });
        } catch (\Throwable $e) {
            // Hapus file yang sudah terlanjur diupload agar tidak jadi orphan.
            if ($newFilePath !== null) {
                Storage::disk('local')->delete($newFilePath);
            }

            throw $e;
        }

        // Hapus file lama setelah data baru berhasil disimpan.
        if (
            $newFilePath !== null
            && $oldFilePath !== null
            && $oldFilePath !== $newFilePath
        ) {
            Storage::disk('local')->delete($oldFilePath);
        }

        $statusCode = $isNew ? 201 : 200;

        return response()->json([
            'message' => 'Tugas berhasil dikumpulkan.',
            'data' => [
                'id' => $submission->id,
                'assessment_id' => $submission->assessment_id,
                'user_id' => $submission->user_id,
                'answer_text' => $submission->answer_text,
                'file_url' => $submission->file_url,
                'submitted_at' => $submission->submitted_at,
                'score' => $submission->score,
                'feedback' => $submission->feedback,
                'correct_count' => $correctCount,
                'total_questions' => $totalQuestions,
            ],
        ], $statusCode);
    }

    public function index(
        Request $request,
        Assessment $assessment
    ): JsonResponse {
        // Hanya mentor dan admin yang boleh melihat daftar submission.
        abort_unless(
            $request->user()->hasAnyRole(['mentor', 'admin']),
            403,
            'Kamu tidak memiliki izin untuk melihat submission.'
        );

        // Batasi ke lembaga pemilik modul (kecuali platform admin).
        $assessment->loadMissing('module');

        abort_unless(
            $request->user()->canAccessInstitution(
                $assessment->module?->institution_id
            ),
            403,
            'Kamu tidak memiliki izin untuk melihat submission assessment ini.'
        );

        $submissions = $assessment->submissions()
            ->with('user:id,name,email')
            ->orderByDesc('submitted_at')
            ->paginate(15);

        return response()->json([
            'message' => 'Daftar pengumpulan tugas berhasil diambil.',
            'data' => $submissions,
        ]);
    }

    public function grade(
        Request $request,
        Submission $submission
    ): JsonResponse {
        abort_unless(
            $request->user()->hasAnyRole(['mentor', 'admin']),
            403,
            'Kamu tidak memiliki izin untuk menilai tugas.'
        );

        $validated = $request->validate([
            'score' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $submission->load('assessment.module', 'user');

        // Batasi ke lembaga pemilik modul (kecuali platform admin).
        abort_unless(
            $request->user()->canAccessInstitution(
                $submission->assessment?->module?->institution_id
            ),
            403,
            'Kamu tidak memiliki izin untuk menilai submission ini.'
        );

        $maxScore = (float) $submission->assessment->max_score;

        if ((float) $validated['score'] > $maxScore) {
            throw ValidationException::withMessages([
                'score' => [
                    'Nilai tidak boleh melebihi nilai maksimal penilaian.',
                ],
            ]);
        }

        $submission->score = $validated['score'];
        $submission->feedback = $validated['feedback'] ?? null;
        $submission->save();

        // Gunakan passing_score dari assessment jika diisi, fallback ke 70% dari max_score.
        $passingScore = $submission->assessment->passing_score;

        $threshold = $passingScore !== null
            ? (float) $passingScore
            : $maxScore * 0.7;

        event(new TaskGraded(
            user: $submission->user,
            submissionId: $submission->id,
            grade: (float) $submission->score,
            threshold: $threshold,
        ));

        return response()->json([
            'message' => 'Penilaian berhasil disimpan.',
            'data' => [
                'id' => $submission->id,
                'assessment_id' => $submission->assessment_id,
                'user_id' => $submission->user_id,
                'score' => $submission->score,
                'feedback' => $submission->feedback,
            ],
        ]);
    }

    /**
     * Apakah modul ini terikat pada minimal satu paket?
     */
    private function isModulePackaged(int $moduleId): bool
    {
        return DB::table('package_module')
            ->where('module_id', $moduleId)
            ->exists();
    }

    /**
     * Apakah user ter-enroll di salah satu paket yang memuat modul ini?
     */
    private function userEnrolledInModule(User $user, int $moduleId): bool
    {
        return Enrollment::where('user_id', $user->id)
            ->whereHas('package.modules', function ($query) use ($moduleId) {
                $query->where('modules.id', $moduleId);
            })
            ->exists();
    }
}
