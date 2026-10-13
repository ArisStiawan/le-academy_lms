<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CertificateController extends Controller
{
    /**
     * Siswa mengajukan permohonan sertifikat.
     * Syarat: progress_pct harus 100%.
     */
    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
        ]);

        $enrollment = Enrollment::with('package:id,title')
            ->where('id', $validated['enrollment_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (! $enrollment->isCompleted()) {
            throw ValidationException::withMessages([
                'enrollment_id' => [
                    'Progres belajar harus 100% untuk mengajukan sertifikat. '
                    ."Progres Anda saat ini: {$enrollment->progress_pct}%.",
                ],
            ]);
        }

        $existing = Certificate::where('enrollment_id', $enrollment->id)->first();

        if ($existing) {
            return response()->json([
                'message' => 'Permohonan sertifikat sudah pernah diajukan.',
                'data' => ['certificate_id' => $existing->id, 'status' => $existing->status],
            ], 409);
        }

        $certificate = Certificate::create([
            'user_id' => $request->user()->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Permohonan sertifikat berhasil diajukan. Menunggu persetujuan admin.',
            'data' => [
                'certificate_id' => $certificate->id,
                'package' => $enrollment->package->only('id', 'title'),
                'status' => $certificate->status,
            ],
        ], 201);
    }

    /**
     * Admin/mentor menyetujui sertifikat dan men-generate kode unik.
     */
    public function approve(Request $request, Certificate $certificate): JsonResponse
    {
        abort_unless(
            $request->user()->hasAnyRole(['admin', 'mentor', 'lembaga']),
            403,
            'Hanya admin, mentor, atau lembaga yang dapat menyetujui sertifikat.'
        );

        $certificate->loadMissing('enrollment.package');

        abort_unless(
            $request->user()->canAccessInstitution(
                $certificate->enrollment?->package?->institution_id
            ),
            403,
            'Kamu tidak memiliki izin untuk memproses sertifikat ini.'
        );

        if ($certificate->status !== 'pending') {
            return response()->json([
                'message' => "Sertifikat ini sudah diproses (status: {$certificate->status}).",
            ], 422);
        }

        $certificate->status = 'approved';
        $certificate->certificate_code = Certificate::generateCode();
        $certificate->issued_at = now();
        $certificate->notes = null;
        $certificate->save();

        return response()->json([
            'message' => 'Sertifikat berhasil disetujui.',
            'data' => [
                'certificate_id' => $certificate->id,
                'certificate_code' => $certificate->certificate_code,
                'issued_at' => $certificate->issued_at,
                'user_id' => $certificate->user_id,
            ],
        ]);
    }

    /**
     * Admin/mentor menolak sertifikat dengan alasan.
     */
    public function reject(Request $request, Certificate $certificate): JsonResponse
    {
        abort_unless(
            $request->user()->hasAnyRole(['admin', 'mentor', 'lembaga']),
            403,
            'Hanya admin, mentor, atau lembaga yang dapat menolak sertifikat.'
        );

        $certificate->loadMissing('enrollment.package');

        abort_unless(
            $request->user()->canAccessInstitution(
                $certificate->enrollment?->package?->institution_id
            ),
            403,
            'Kamu tidak memiliki izin untuk memproses sertifikat ini.'
        );

        if ($certificate->status !== 'pending') {
            return response()->json([
                'message' => "Sertifikat ini sudah diproses (status: {$certificate->status}).",
            ], 422);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $certificate->status = 'rejected';
        $certificate->notes = $validated['notes'] ?? null;
        $certificate->save();

        return response()->json([
            'message' => 'Sertifikat ditolak.',
            'data' => [
                'certificate_id' => $certificate->id,
                'status' => $certificate->status,
                'notes' => $certificate->notes,
            ],
        ]);
    }

    /**
     * Daftar semua sertifikat (admin/mentor/lembaga). Bisa filter by status.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasAnyRole(['admin', 'mentor', 'lembaga']),
            403,
            'Hanya admin, mentor, atau lembaga yang dapat melihat semua sertifikat.'
        );

        $certs = Certificate::with(
            'user:id,name,email',
            'enrollment.package:id,title'
        )
            ->when(
                ! $request->user()->isPlatformAdmin(),
                fn ($q) => $q->whereHas(
                    'enrollment.package',
                    fn ($p) => $p->where('institution_id', $request->user()->institution_id)
                )
            )
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15);

        return response()->json([
            'message' => 'Daftar sertifikat berhasil diambil.',
            'data' => $certs,
        ]);
    }

    /**
     * Sertifikat milik siswa yang sedang login.
     */
    public function mine(Request $request): JsonResponse
    {
        $certs = Certificate::with('enrollment.package:id,title')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Sertifikat Anda berhasil diambil.',
            'data' => $certs,
        ]);
    }

    /**
     * Verifikasi sertifikat secara publik via kode — tidak butuh login.
     */
    public function verify(string $code): JsonResponse
    {
        $cert = Certificate::with(
            'user:id,name',
            'enrollment.package:id,title'
        )
            ->where('certificate_code', strtoupper($code))
            ->where('status', 'approved')
            ->first();

        if (! $cert) {
            return response()->json([
                'valid' => false,
                'message' => 'Kode sertifikat tidak valid atau belum disetujui.',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'data' => [
                'certificate_code' => $cert->certificate_code,
                'holder' => $cert->user->name,
                'package' => $cert->enrollment->package->title,
                'issued_at' => $cert->issued_at,
            ],
        ]);
    }
}
