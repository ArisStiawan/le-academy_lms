<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Daftar jadwal (bimbingan/webinar) — publik untuk yang aktif.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $registeredIds = $user
            ? $user->schedules()->pluck('schedules.id')->flip()
            : collect();

        $schedules = Schedule::with('mentor:id,name')
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when(
                ! $user?->isPlatformAdmin(),
                fn ($q) => $q->where('is_active', true)
            )
            ->where('start_at', '>=', now()->subDay())
            ->orderBy('start_at')
            ->paginate(15);

        // Tandai jadwal mana yang sudah diikuti user (untuk tombol daftar/batal).
        $schedules->getCollection()->each(function (Schedule $schedule) use ($registeredIds) {
            $schedule->setAttribute('is_registered', $registeredIds->has($schedule->id));
        });

        return response()->json([
            'message' => 'Daftar jadwal berhasil diambil.',
            'data' => $schedules,
        ]);
    }

    /**
     * Detail jadwal + jumlah peserta.
     */
    public function show(Request $request, Schedule $schedule): JsonResponse
    {
        abort_unless(
            $schedule->is_active || $request->user()?->isPlatformAdmin(),
            403,
            'Jadwal ini tidak aktif.'
        );

        $schedule->loadCount('participants');

        return response()->json([
            'message' => 'Detail jadwal berhasil diambil.',
            'data' => $schedule,
        ]);
    }

    /**
     * Buat jadwal baru — hanya admin platform atau pengelola lembaga.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isPlatformAdmin() || $user->hasRole('mentor') || $user->hasRole('lembaga'),
            403,
            'Kamu tidak berhak membuat jadwal.'
        );

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:bimbingan,webinar'],
            'description' => ['nullable', 'string'],
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'zoom_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $schedule = Schedule::create([
            'institution_id' => $user->isPlatformAdmin() ? ($request->institution_id ?? null) : $user->institution_id,
            'mentor_id' => $request->mentor_id ?? $user->id,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'zoom_url' => $validated['zoom_url'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Jadwal berhasil dibuat.',
            'data' => $schedule,
        ], 201);
    }

    /**
     * Update jadwal — hanya pengelola (admin platform / lembaga yang sama).
     */
    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isPlatformAdmin() || $schedule->institution_id === $user->institution_id,
            403,
            'Kamu tidak berhak mengubah jadwal ini.'
        );

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:bimbingan,webinar'],
            'description' => ['sometimes', 'nullable', 'string'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'nullable', 'date', 'after:start_at'],
            'zoom_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $schedule->update($validated);

        return response()->json([
            'message' => 'Jadwal berhasil diperbarui.',
            'data' => $schedule,
        ]);
    }

    /**
     * Hapus jadwal — hanya pengelola.
     */
    public function destroy(Request $request, Schedule $schedule): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isPlatformAdmin() || $schedule->institution_id === $user->institution_id,
            403,
            'Kamu tidak berhak menghapus jadwal ini.'
        );

        $schedule->delete();

        return response()->json([
            'message' => 'Jadwal berhasil dihapus.',
        ]);
    }

    /**
     * Daftar ke jadwal (webinar/bimbingan) — user mana pun yang login.
     */
    public function register(Request $request, Schedule $schedule): JsonResponse
    {
        if (! $schedule->is_active) {
            return response()->json(['message' => 'Jadwal ini tidak aktif.'], 422);
        }

        if ($schedule->start_at->isPast()) {
            return response()->json(['message' => 'Jadwal sudah dimulai.'], 422);
        }

        $user = $request->user();

        $already = $schedule->participants()->where('user_id', $user->id)->exists();
        if ($already) {
            return response()->json([
                'message' => 'Kamu sudah terdaftar di jadwal ini.',
                'data' => ['is_registered' => true],
            ], 200);
        }

        $schedule->participants()->attach($user->id);

        return response()->json([
            'message' => 'Berhasil mendaftar ke jadwal.',
            'data' => ['is_registered' => true, 'zoom_url' => $schedule->zoom_url],
        ], 201);
    }

    /**
     * Batalkan pendaftaran.
     */
    public function unregister(Request $request, Schedule $schedule): JsonResponse
    {
        $user = $request->user();
        $schedule->participants()->detach($user->id);

        return response()->json([
            'message' => 'Pendaftaran dibatalkan.',
            'data' => ['is_registered' => false],
        ]);
    }

    /**
     * Jadwal yang saya ikuti.
     */
    public function mySchedules(Request $request): JsonResponse
    {
        $schedules = $request->user()
            ->schedules()
            ->when($request->type, fn ($q) => $q->where('schedules.type', $request->type))
            ->with('mentor:id,name')
            ->orderBy('start_at')
            ->paginate(15);

        $schedules->getCollection()->each(
            fn (Schedule $schedule) => $schedule->setAttribute('is_registered', true)
        );

        return response()->json([
            'message' => 'Jadwal saya berhasil diambil.',
            'data' => $schedules,
        ]);
    }
}
