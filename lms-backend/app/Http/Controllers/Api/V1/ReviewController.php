<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Daftar ulasan sebuah paket (publik).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);

        $reviews = Review::with('user:id,name')
            ->where('package_id', $validated['package_id'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Ulasan berhasil diambil.',
            'data' => $reviews,
        ]);
    }

    /**
     * Beri ulasan — hanya siswa yang pernah ter-enroll di paket tsb.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $package = Package::findOrFail($validated['package_id']);

        // Harus pernah membeli/menjadi peserta paket ini
        $isEnrolled = $user->enrollments()
            ->where('package_id', $package->id)
            ->exists();

        abort_unless($isEnrolled, 403, 'Kamu harus terdaftar di paket ini sebelum memberi ulasan.');

        // Cegah ulasan ganda (unique user_id + package_id)
        if (Review::where('user_id', $user->id)->where('package_id', $package->id)->exists()) {
            return response()->json([
                'message' => 'Kamu sudah memberi ulasan untuk paket ini.',
            ], 409);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Ulasan berhasil dikirim.',
            'data' => $review->load('user:id,name'),
        ], 201);
    }

    /**
     * Hapus ulasan sendiri.
     */
    public function destroy(Request $request, Review $review): JsonResponse
    {
        $user = $request->user();

        // Pemilik review, atau platform admin
        $isOwner = $review->user_id === $user->id;
        $isAdmin = $user->hasRole('admin');

        abort_unless($isOwner || $isAdmin, 403, 'Kamu tidak berhak menghapus ulasan ini.');

        $review->delete();

        return response()->json([
            'message' => 'Ulasan berhasil dihapus.',
        ]);
    }
}
