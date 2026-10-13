<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Daftar notifikasi milik user yang login.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'message' => 'Daftar notifikasi berhasil diambil.',
            'data' => $notifications,
        ]);
    }

    /**
     * Tandai satu notifikasi sudah dibaca.
     */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless(
            $notification->user_id === $request->user()->id,
            403,
            'Notifikasi ini bukan milik Anda.'
        );

        if (! $notification->is_read) {
            $notification->is_read = true;
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json([
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'data' => $notification,
        ]);
    }

    /**
     * Tandai semua notifikasi sudah dibaca.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
        ]);
    }

    /**
     * Hapus notifikasi.
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        abort_unless(
            $notification->user_id === $request->user()->id,
            403,
            'Notifikasi ini bukan milik Anda.'
        );

        $notification->delete();

        return response()->json([
            'message' => 'Notifikasi berhasil dihapus.',
        ]);
    }
}
