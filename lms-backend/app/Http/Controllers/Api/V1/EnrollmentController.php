<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    /**
     * Daftar paket yang diikuti user yang login (beserta progres & sertifikat).
     */
    public function index(Request $request): JsonResponse
    {
        $enrollments = Enrollment::with(
            'package:id,title,description,price',
            'certificate:id,enrollment_id,status,certificate_code,issued_at'
        )
            ->where('user_id', $request->user()->id)
            ->latest('enrolled_at')
            ->get();

        return response()->json([
            'message' => 'Daftar enrollment berhasil diambil.',
            'data' => $enrollments,
        ]);
    }
}
