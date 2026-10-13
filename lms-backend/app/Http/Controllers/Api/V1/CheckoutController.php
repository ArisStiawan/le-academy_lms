<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Package;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /**
     * Preview harga + perhitungan diskon sebelum checkout.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'voucher_code' => ['nullable', 'string'],
        ]);

        $package = Package::findOrFail($validated['package_id']);

        if (! $package->is_active) {
            return response()->json(['message' => 'Paket ini tidak tersedia.'], 422);
        }

        if (Enrollment::where('user_id', $request->user()->id)
            ->where('package_id', $package->id)->exists()
        ) {
            return response()->json(['message' => 'Anda sudah terdaftar di paket ini.'], 422);
        }

        $originalPrice = $package->price;
        $discountAmount = 0.0;
        $voucher = null;

        if (! empty($validated['voucher_code'])) {
            $voucher = Voucher::where('code', $validated['voucher_code'])->first();

            if (! $voucher || ! $voucher->isUsable()) {
                throw ValidationException::withMessages([
                    'voucher_code' => ['Voucher tidak valid atau sudah kedaluwarsa.'],
                ]);
            }
            $discountAmount = $voucher->calculateDiscount($originalPrice);
        }

        return response()->json([
            'data' => [
                'package' => $package->only('id', 'title', 'description'),
                'original_price' => $originalPrice,
                'voucher' => $voucher?->only('code', 'discount_type', 'discount_value'),
                'discount_amount' => $discountAmount,
                'final_price' => max(0, $originalPrice - $discountAmount),
            ],
        ]);
    }

    /**
     * Buat order (status: pending). User belum ter-enroll di sini.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'voucher_code' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $package = Package::findOrFail($validated['package_id']);

        if (! $package->is_active) {
            return response()->json(['message' => 'Paket ini tidak tersedia.'], 422);
        }

        if (Enrollment::where('user_id', $user->id)->where('package_id', $package->id)->exists()) {
            return response()->json(['message' => 'Anda sudah terdaftar di paket ini.'], 422);
        }

        // Cegah duplikasi order pending
        $existing = Order::where('user_id', $user->id)
            ->where('package_id', $package->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Anda sudah memiliki order yang menunggu pembayaran untuk paket ini.',
                'data' => ['order_id' => $existing->id],
            ], 409);
        }

        $originalPrice = $package->price;
        $discountAmount = 0.0;
        $voucherId = null;

        $order = DB::transaction(function () use (
            $user, $package, $validated, $originalPrice, &$discountAmount, &$voucherId
        ) {
            if (! empty($validated['voucher_code'])) {
                $voucher = Voucher::where('code', $validated['voucher_code'])
                    ->lockForUpdate()
                    ->first();

                if (! $voucher || ! $voucher->isUsable()) {
                    throw ValidationException::withMessages([
                        'voucher_code' => ['Voucher tidak valid atau sudah kedaluwarsa.'],
                    ]);
                }

                $discountAmount = $voucher->calculateDiscount($originalPrice);
                $voucherId = $voucher->id;
            }

            return Order::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'voucher_id' => $voucherId,
                'original_price' => $originalPrice,
                'discount_amount' => $discountAmount,
                'final_price' => max(0, $originalPrice - $discountAmount),
                'status' => 'pending',
            ]);
        });

        return response()->json([
            'message' => 'Order berhasil dibuat. Lanjutkan ke pembayaran.',
            'data' => [
                'order_id' => $order->id,
                'package' => $package->only('id', 'title'),
                'original_price' => $order->original_price,
                'discount_amount' => $order->discount_amount,
                'final_price' => $order->final_price,
                'status' => $order->status,
            ],
        ], 201);
    }

    /**
     * Simulasi pembayaran: tandai order lunas → trigger enrollment otomatis.
     */
    public function pay(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        abort_unless($order->user_id === $user->id, 403, 'Order ini bukan milik Anda.');

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => "Order ini sudah diproses (status: {$order->status}).",
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->status = 'paid';
            $order->paid_at = now();
            $order->save();

            // Tandai voucher terpakai (increment atomic)
            if ($order->voucher_id) {
                $order->voucher()->increment('used_count');
            }
        });

        // Fire event → EnrollUserOnPayment listener akan buat enrollment
        event(new OrderPaid(user: $user, order: $order->fresh()));

        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('package_id', $order->package_id)
            ->first();

        return response()->json([
            'message' => 'Pembayaran berhasil. Anda sekarang terdaftar di paket ini.',
            'data' => [
                'order_id' => $order->id,
                'status' => 'paid',
                'paid_at' => $order->paid_at,
                'enrollment_id' => $enrollment?->id,
            ],
        ]);
    }

    /**
     * Riwayat semua order milik user yang login.
     */
    public function myOrders(Request $request): JsonResponse
    {
        $orders = Order::with('package:id,title', 'voucher:id,code')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Riwayat order berhasil diambil.',
            'data' => $orders,
        ]);
    }
}
