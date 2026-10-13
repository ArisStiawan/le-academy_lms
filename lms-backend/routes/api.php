<?php

use App\Http\Controllers\Api\V1\AssessmentSubmissionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CertificateController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CurriculumController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\ForumController;
use App\Http\Controllers\Api\V1\InstitutionController;
use App\Http\Controllers\Api\V1\LeaderboardController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\StudentLearningController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────
// Public routes (tidak butuh login)
// ─────────────────────────────────────────
Route::prefix('v1')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:register');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login');
    });

    // Undangan lembaga — diakses melalui link email, tidak butuh token
    Route::get('/institution/invites/{token}', [InstitutionController::class, 'showInvite']);
    Route::post('/institution/invites/{token}/accept', [InstitutionController::class, 'acceptInvite']);

    // Verifikasi sertifikat — endpoint publik untuk pihak ketiga
    Route::get('/certificates/verify/{code}', [CertificateController::class, 'verify']);

    // Paket belajar — bisa dilihat tanpa login
    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/packages/{package}', [PackageController::class, 'show']);

    // Ulasan paket — publik
    Route::get('/reviews', [ReviewController::class, 'index']);

    // Jadwal bimbingan/webinar — publik (yang aktif)
    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::get('/schedules/{schedule}', [ScheduleController::class, 'show']);
});

// ─────────────────────────────────────────
// Protected routes (butuh Sanctum token)
// ─────────────────────────────────────────
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // ── Assessment & Submission ──────────────────
    Route::post('/assessments/{assessment}/submit',
        [AssessmentSubmissionController::class, 'submit']);
    Route::get('/assessments/{assessment}/submissions',
        [AssessmentSubmissionController::class, 'index']);
    Route::patch('/submissions/{submission}/grade',
        [AssessmentSubmissionController::class, 'grade']);

    // ── Institution B2B ──────────────────────────
    Route::prefix('institution')->group(function () {
        Route::post('/students/import', [InstitutionController::class, 'import']);
        Route::post('/students/invite', [InstitutionController::class, 'invite']);
    });

    // ── Commerce: Checkout & Orders ──────────────
    Route::get('/checkout/preview', [CheckoutController::class, 'preview']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/orders/{order}/pay', [CheckoutController::class, 'pay']);
    Route::get('/orders', [CheckoutController::class, 'myOrders']);

    // ── Transaksi (pengelola) ────────────────────
    Route::get('/orders/manage', [OrderController::class, 'index']);
    Route::get('/orders/summary', [OrderController::class, 'summary']);

    // ── Sertifikat ───────────────────────────────
    Route::post('/certificates/request', [CertificateController::class, 'request']);
    Route::get('/certificates/mine', [CertificateController::class, 'mine']);
    Route::get('/certificates', [CertificateController::class, 'index']);
    Route::patch('/certificates/{certificate}/approve', [CertificateController::class, 'approve']);
    Route::patch('/certificates/{certificate}/reject', [CertificateController::class, 'reject']);

    // ── Kurikulum & Konten (CRUD, butuh token) ─────
    Route::get('/curricula', [CurriculumController::class, 'index']);
    Route::get('/curricula/{curriculum}', [CurriculumController::class, 'show']);
    Route::post('/curricula', [CurriculumController::class, 'store']);
    Route::put('/curricula/{curriculum}', [CurriculumController::class, 'update']);
    Route::delete('/curricula/{curriculum}', [CurriculumController::class, 'destroy']);
    Route::post('/curricula/{curriculum}/modules', [CurriculumController::class, 'storeModule']);
    Route::post('/modules/{module}/contents', [CurriculumController::class, 'storeContent']);
    Route::post('/modules/{module}/assessments', [CurriculumController::class, 'storeAssessment']);
    Route::post('/contents/{content}/complete', [CurriculumController::class, 'completeContent']);

    // ── Pembelajaran siswa: materi & tugas/ujian ─────
    Route::get('/my/materials', [StudentLearningController::class, 'materials']);
    Route::get('/my/materials/{content}', [StudentLearningController::class, 'materialShow']);
    Route::get('/my/assessments', [StudentLearningController::class, 'assessments']);
    Route::get('/my/assessments/{assessment}', [StudentLearningController::class, 'assessmentShow']);

    // ── Enrollment (paket yang saya ikuti) ──────────
    Route::get('/my/enrollments', [EnrollmentController::class, 'index']);

    // ── Paket (pengelola: termasuk non-aktif) ───────
    Route::get('/manage/packages', [PackageController::class, 'manage']);

    // ── Pengguna ────────────────────────────────────
    Route::get('/users', [UserController::class, 'index']);

    // ── Gamifikasi: Leaderboard ────────────────────
    Route::get('/gamification/leaderboard', [LeaderboardController::class, 'index']);

    // ── Notifikasi ─────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    // ── Ulasan ──────────────────────────────────────
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);

    // ── Forum Diskusi ───────────────────────────────
    Route::get('/forum/threads', [ForumController::class, 'threads']);
    Route::get('/forum/threads/{thread}', [ForumController::class, 'showThread']);
    Route::post('/forum/threads', [ForumController::class, 'storeThread']);
    Route::post('/forum/posts', [ForumController::class, 'storePost']);
    Route::patch('/forum/threads/{thread}/toggle-close', [ForumController::class, 'toggleClose']);
    Route::delete('/forum/threads/{thread}', [ForumController::class, 'destroyThread']);
    Route::delete('/forum/posts/{post}', [ForumController::class, 'destroyPost']);

    // ── Jadwal Bimbingan/Webinar ────────────────────
    Route::post('/schedules', [ScheduleController::class, 'store']);
    Route::put('/schedules/{schedule}', [ScheduleController::class, 'update']);
    Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy']);
    Route::post('/schedules/{schedule}/register', [ScheduleController::class, 'register']);
    Route::post('/schedules/{schedule}/unregister', [ScheduleController::class, 'unregister']);
    Route::get('/my/schedules', [ScheduleController::class, 'mySchedules']);
});
