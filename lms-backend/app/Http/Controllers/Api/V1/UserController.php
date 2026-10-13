<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Daftar pengguna. Admin platform melihat semua; mentor/lembaga hanya
     * pengguna di lembaganya sendiri. Bisa difilter dengan ?role=siswa.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasAnyRole(['admin', 'mentor', 'lembaga']),
            403,
            'Kamu tidak berhak melihat daftar pengguna.'
        );

        $users = User::with('roles:id,name', 'institution:id,name')
            ->when(
                ! $user->isPlatformAdmin(),
                fn ($q) => $q->where('institution_id', $user->institution_id)
            )
            ->when(
                $request->role,
                fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $request->role))
            )
            ->when(
                $request->search,
                fn ($q) => $q->where(function ($w) use ($request) {
                    $needle = '%'.mb_strtolower($request->search).'%';
                    $w->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
                })
            )
            ->latest()
            ->paginate(20);

        return response()->json([
            'message' => 'Daftar pengguna berhasil diambil.',
            'data' => $users,
        ]);
    }
}
