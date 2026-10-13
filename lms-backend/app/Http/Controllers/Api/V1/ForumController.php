<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\ForumPost;
use App\Models\ForumThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    /**
     * Daftar thread diskusi dalam sebuah kurikulum.
     * Terbuka untuk siapa saja yang bisa mengakses kurikulum tsb.
     */
    public function threads(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'curriculum_id' => ['required', 'integer', 'exists:curricula,id'],
        ]);

        $curriculum = Curriculum::findOrFail($validated['curriculum_id']);
        abort_unless(
            $request->user()->canAccessInstitution($curriculum->institution_id),
            403,
            'Kamu tidak memiliki izin untuk mengakses kurikulum ini.'
        );

        $threads = $curriculum->threads()
            ->with('user:id,name')
            ->withCount('posts')
            ->orderByDesc('is_pinned')
            ->latest('updated_at')
            ->paginate(15);

        return response()->json([
            'message' => 'Thread forum berhasil diambil.',
            'data' => $threads,
        ]);
    }

    /**
     * Detail thread + semua balasan.
     */
    public function showThread(Request $request, ForumThread $thread): JsonResponse
    {
        $curriculum = $thread->curriculum;
        abort_unless(
            $request->user()->canAccessInstitution($curriculum->institution_id),
            403,
            'Kamu tidak memiliki izin untuk mengakses thread ini.'
        );

        $thread->load(['user:id,name', 'posts.user:id,name']);
        $thread->loadCount('posts');

        return response()->json([
            'message' => 'Detail thread berhasil diambil.',
            'data' => $thread,
        ]);
    }

    /**
     * Buat thread baru dalam kurikulum.
     */
    public function storeThread(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'curriculum_id' => ['required', 'integer', 'exists:curricula,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $curriculum = Curriculum::findOrFail($validated['curriculum_id']);
        abort_unless(
            $request->user()->canAccessInstitution($curriculum->institution_id),
            403,
            'Kamu tidak memiliki izin untuk membuat thread di kurikulum ini.'
        );

        $thread = $curriculum->threads()->create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return response()->json([
            'message' => 'Thread berhasil dibuat.',
            'data' => $thread->load('user:id,name'),
        ], 201);
    }

    /**
     * Balas thread.
     */
    public function storePost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'thread_id' => ['required', 'integer', 'exists:forum_threads,id'],
            'body' => ['required', 'string'],
        ]);

        $thread = ForumThread::findOrFail($validated['thread_id']);
        $curriculum = $thread->curriculum;
        abort_unless(
            $request->user()->canAccessInstitution($curriculum->institution_id),
            403,
            'Kamu tidak memiliki izin untuk membalas thread ini.'
        );

        // Thread yang sudah ditutup tidak bisa dibalas (kecuali pemilik kurikulum)
        if ($thread->is_closed && ! $request->user()->canAccessInstitution($curriculum->institution_id)) {
            return response()->json(['message' => 'Thread ini sudah ditutup.'], 422);
        }

        $post = $thread->posts()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        // Sentuh updated_at thread agar naik ke urutan teratas (latest('updated_at'))
        $thread->touch();

        return response()->json([
            'message' => 'Balasan berhasil dikirim.',
            'data' => $post->load('user:id,name'),
        ], 201);
    }

    /**
     * Tutup/buka thread — hanya pemilik kurikulum (mentor/lembaga/admin).
     */
    public function toggleClose(Request $request, ForumThread $thread): JsonResponse
    {
        $curriculum = $thread->curriculum;
        $user = $request->user();

        // Hanya pengelola kurikulum (admin platform / mentor / lembaga) yang boleh
        // menutup/membuka thread — siswa biasa (meski satu lembaga) tidak.
        $isStaff = $user->isPlatformAdmin()
            || $user->hasAnyRole(['mentor', 'lembaga']);

        abort_unless(
            $isStaff && $user->canAccessInstitution($curriculum->institution_id),
            403,
            'Kamu tidak memiliki izin untuk mengubah status thread ini.'
        );

        $thread->is_closed = ! $thread->is_closed;
        $thread->save();

        return response()->json([
            'message' => $thread->is_closed ? 'Thread ditutup.' : 'Thread dibuka kembali.',
            'data' => ['is_closed' => $thread->is_closed],
        ]);
    }

    /**
     * Hapus thread — pemilik thread atau pengelola kurikulum.
     */
    public function destroyThread(Request $request, ForumThread $thread): JsonResponse
    {
        $curriculum = $thread->curriculum;
        $user = $request->user();

        $canManage = $user->canAccessInstitution($curriculum->institution_id);
        $isOwner = $thread->user_id === $user->id;

        abort_unless($canManage || $isOwner, 403, 'Kamu tidak berhak menghapus thread ini.');

        $thread->delete();

        return response()->json([
            'message' => 'Thread berhasil dihapus.',
        ]);
    }

    /**
     * Hapus balasan — pemilik balasan atau pengelola kurikulum.
     */
    public function destroyPost(Request $request, ForumPost $post): JsonResponse
    {
        $thread = $post->thread;
        $curriculum = $thread->curriculum;
        $user = $request->user();

        $canManage = $user->canAccessInstitution($curriculum->institution_id);
        $isOwner = $post->user_id === $user->id;

        abort_unless($canManage || $isOwner, 403, 'Kamu tidak berhak menghapus balasan ini.');

        $post->delete();

        return response()->json([
            'message' => 'Balasan berhasil dihapus.',
        ]);
    }
}
