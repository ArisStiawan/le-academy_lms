<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    /**
     * Daftar paket aktif beserta modul di dalamnya.
     */
    public function index(Request $request): JsonResponse
    {
        $packages = Package::with('modules:id,title,description')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'message' => 'Daftar paket berhasil diambil.',
            'data' => $packages,
        ]);
    }

    /**
     * Detail satu paket.
     */
    public function show(Package $package): JsonResponse
    {
        abort_unless($package->is_active, 404);

        $package->load('modules:id,title,description');

        return response()->json([
            'message' => 'Detail paket berhasil diambil.',
            'data' => $package,
        ]);
    }

    /**
     * Daftar paket untuk pengelola (termasuk non-aktif), ter-scope per lembaga.
     */
    public function manage(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasAnyRole(['admin', 'mentor', 'lembaga']),
            403,
            'Kamu tidak berhak mengelola paket.'
        );

        $packages = Package::withCount('enrollments', 'modules')
            ->when(
                ! $user->isPlatformAdmin(),
                fn ($q) => $q->where('institution_id', $user->institution_id)
            )
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Daftar paket (pengelola) berhasil diambil.',
            'data' => $packages,
        ]);
    }
}
