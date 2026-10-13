<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\InstitutionInvite;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InstitutionController extends Controller
{
    /**
     * Import massal siswa ke dalam paket.
     * Jika email belum terdaftar, akun baru dibuat otomatis dengan password sementara.
     * Password sementara HARUS dikirim melalui email (belum diimplementasikan),
     * bukan dikembalikan di response API.
     */
    public function import(Request $request): JsonResponse
    {
        $user = $request->user();

        // Lembaga Bimbingan wajib bisa mendaftarkan siswanya sendiri (PRD §E).
        abort_unless(
            $user->hasAnyRole(['admin', 'mentor', 'lembaga']),
            403,
            'Hanya admin, mentor, atau lembaga yang dapat mengimpor siswa.'
        );

        if (! $user->isPlatformAdmin() && ! $user->institution_id) {
            return response()->json([
                'message' => 'Akun Anda tidak terhubung dengan lembaga manapun.',
            ], 422);
        }

        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'emails' => ['required', 'array', 'min:1', 'max:100'],
            'emails.*' => ['required', 'email', 'max:255'],
        ]);

        $package = Package::findOrFail($validated['package_id']);

        // Cegah lintas lembaga: non-platform-admin hanya boleh paket miliknya
        // (paket global / tanpa lembaga tetap boleh).
        abort_unless(
            $user->isPlatformAdmin()
                || is_null($package->institution_id)
                || $package->institution_id === $user->institution_id,
            403,
            'Kamu hanya dapat mendaftarkan siswa ke paket lembaga Anda.'
        );

        $institutionId = $package->institution_id ?? $user->institution_id;

        $results = DB::transaction(function () use ($validated, $package, $institutionId) {
            $created = [];
            $enrolled = [];
            $skipped = [];

            foreach ($validated['emails'] as $raw) {
                $email = strtolower(trim($raw));
                $isNew = false;

                $user = User::where('email', $email)->first();

                if (! $user) {
                    $tempPassword = Str::random(12);
                    $user = User::create([
                        'name' => explode('@', $email)[0],
                        'email' => $email,
                        'password' => Hash::make($tempPassword),
                        'institution_id' => $institutionId,
                        'is_active' => true,
                    ]);
                    $user->assignRole('siswa');
                    // Catatan: $tempPassword harus dikirim via email, jangan di response.
                    $created[] = ['email' => $email];
                    $isNew = true;
                }

                $alreadyEnrolled = Enrollment::where('user_id', $user->id)
                    ->where('package_id', $package->id)
                    ->exists();

                if ($alreadyEnrolled) {
                    $skipped[] = $email;

                    continue;
                }

                Enrollment::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'progress_pct' => 0,
                    'enrolled_at' => now(),
                ]);

                if (! $isNew) {
                    $enrolled[] = $email;
                }
            }

            return compact('created', 'enrolled', 'skipped');
        });

        return response()->json([
            'message' => 'Import siswa selesai.',
            'data' => [
                'package' => $package->only('id', 'title'),
                'summary' => [
                    'created_and_enrolled' => count($results['created']),
                    'enrolled_existing' => count($results['enrolled']),
                    'skipped_already_enrolled' => count($results['skipped']),
                ],
                // Daftar email akun baru. Password sementara dikirim via email (TODO).
                'new_accounts' => $results['created'],
            ],
        ], 201);
    }

    /**
     * Kirim undangan bergabung ke satu atau beberapa email.
     * Menghasilkan token unik per email untuk link penerimaan undangan.
     */
    public function invite(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasAnyRole(['admin', 'mentor', 'lembaga']),
            403,
            'Hanya admin, mentor, atau lembaga yang dapat mengirim undangan.'
        );

        $institutionId = $request->user()->institution_id;

        if (! $institutionId) {
            return response()->json([
                'message' => 'Akun Anda tidak terhubung dengan lembaga manapun.',
            ], 422);
        }

        $validated = $request->validate([
            'emails' => ['required', 'array', 'min:1', 'max:50'],
            'emails.*' => ['required', 'email', 'max:255'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'role' => ['nullable', 'string', 'in:siswa,mentor'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        // Cegah undangan ke paket milik lembaga lain (paket global tetap boleh).
        if (! empty($validated['package_id']) && ! $request->user()->isPlatformAdmin()) {
            $package = Package::find($validated['package_id']);
            abort_unless(
                $package && (is_null($package->institution_id) || $package->institution_id === $institutionId),
                403,
                'Kamu hanya dapat mengundang ke paket lembaga Anda.'
            );
        }

        $expiresAt = now()->addDays($validated['expires_in_days'] ?? 7);
        $role = $validated['role'] ?? 'siswa';
        $packageId = $validated['package_id'] ?? null;

        $invites = [];
        foreach ($validated['emails'] as $raw) {
            $email = strtolower(trim($raw));

            // updateOrCreate: perbarui token & tanggal jika sudah ada undangan sebelumnya
            $invite = InstitutionInvite::updateOrCreate(
                ['institution_id' => $institutionId, 'email' => $email],
                [
                    'token' => Str::random(40),
                    'role' => $role,
                    'package_id' => $packageId,
                    'accepted_at' => null,
                    'expires_at' => $expiresAt,
                ]
            );

            $invites[] = [
                'email' => $email,
                'invite_url' => url("/api/v1/institution/invites/{$invite->token}/accept"),
                'expires_at' => $invite->expires_at,
            ];
        }

        return response()->json([
            'message' => 'Undangan berhasil dibuat.',
            'data' => $invites,
        ], 201);
    }

    /**
     * Tampilkan detail undangan berdasarkan token (akses publik).
     */
    public function showInvite(string $token): JsonResponse
    {
        $invite = InstitutionInvite::with('institution', 'package')
            ->where('token', $token)
            ->firstOrFail();

        if ($invite->isAccepted()) {
            return response()->json(['message' => 'Undangan ini sudah digunakan.'], 410);
        }
        if ($invite->isExpired()) {
            return response()->json(['message' => 'Undangan sudah kedaluwarsa.'], 410);
        }

        return response()->json([
            'data' => [
                'email' => $invite->email,
                'institution' => $invite->institution?->only('id', 'name'),
                'package' => $invite->package?->only('id', 'title'),
                'role' => $invite->role,
                'expires_at' => $invite->expires_at,
            ],
        ]);
    }

    /**
     * Terima undangan: daftarkan akun baru + enroll ke paket (akses publik).
     */
    public function acceptInvite(Request $request, string $token): JsonResponse
    {
        $invite = InstitutionInvite::with('package')
            ->where('token', $token)
            ->firstOrFail();

        if ($invite->isAccepted()) {
            return response()->json(['message' => 'Undangan ini sudah digunakan.'], 410);
        }
        if ($invite->isExpired()) {
            return response()->json(['message' => 'Undangan sudah kedaluwarsa.'], 410);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $result = DB::transaction(function () use ($invite, $validated) {
            $user = User::where('email', $invite->email)->first();

            if ($user) {
                // Akun sudah ada: hubungkan dengan lembaga dan aktifkan
                $user->update([
                    'institution_id' => $invite->institution_id,
                    'is_active' => true,
                ]);
            } else {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $invite->email,
                    'password' => Hash::make($validated['password']),
                    'institution_id' => $invite->institution_id,
                    'is_active' => true,
                ]);
                $user->assignRole($invite->role);
            }

            // Enroll ke paket jika undangan menyertakan paket
            if ($invite->package_id) {
                Enrollment::firstOrCreate(
                    ['user_id' => $user->id, 'package_id' => $invite->package_id],
                    ['progress_pct' => 0, 'enrolled_at' => now()]
                );
            }

            $invite->update(['accepted_at' => now()]);

            return [
                'user' => $user,
                'token' => $user->createToken('auth-token')->plainTextToken,
            ];
        });

        return response()->json([
            'message' => 'Undangan berhasil diterima. Akun Anda sudah aktif.',
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'user' => $result['user']->only('id', 'name', 'email'),
            ],
        ], 201);
    }
}
