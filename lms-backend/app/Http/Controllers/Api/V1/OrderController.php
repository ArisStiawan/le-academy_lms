<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Daftar transaksi. Admin platform melihat semua; lembaga/mentor hanya
     * transaksi paket milik lembaganya.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasAnyRole(['admin', 'mentor', 'lembaga']),
            403,
            'Kamu tidak berhak melihat daftar transaksi.'
        );

        $orders = Order::with('user:id,name,email', 'package:id,title,institution_id')
            ->when(
                ! $user->isPlatformAdmin(),
                fn ($q) => $q->whereHas(
                    'package',
                    fn ($p) => $p->where('institution_id', $user->institution_id)
                )
            )
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return response()->json([
            'message' => 'Daftar transaksi berhasil diambil.',
            'data' => $orders,
        ]);
    }

    /**
     * Ringkasan pemasukan (total pendapatan, jumlah transaksi, per paket).
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasAnyRole(['admin', 'mentor', 'lembaga']),
            403,
            'Kamu tidak berhak melihat ringkasan pemasukan.'
        );

        $base = Order::query()
            ->when(
                ! $user->isPlatformAdmin(),
                fn ($q) => $q->whereHas(
                    'package',
                    fn ($p) => $p->where('institution_id', $user->institution_id)
                )
            );

        $paid = (clone $base)->where('status', 'paid');

        $totalRevenue = (float) (clone $paid)->sum('final_price');
        $paidCount = (clone $paid)->count();
        $pendingCount = (clone $base)->where('status', 'pending')->count();

        $perPackage = Order::query()
            ->select('package_id', DB::raw('SUM(final_price) as revenue'), DB::raw('COUNT(*) as orders'))
            ->with('package:id,title')
            ->where('status', 'paid')
            ->when(
                ! $user->isPlatformAdmin(),
                fn ($q) => $q->whereHas(
                    'package',
                    fn ($p) => $p->where('institution_id', $user->institution_id)
                )
            )
            ->groupBy('package_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'package' => $row->package?->only('id', 'title'),
                'revenue' => (float) $row->revenue,
                'orders' => (int) $row->orders,
            ]);

        return response()->json([
            'message' => 'Ringkasan pemasukan berhasil diambil.',
            'data' => [
                'total_revenue' => $totalRevenue,
                'paid_orders' => $paidCount,
                'pending_orders' => $pendingCount,
                'total_packages' => Package::when(
                    ! $user->isPlatformAdmin(),
                    fn ($q) => $q->where('institution_id', $user->institution_id)
                )->count(),
                'per_package' => $perPackage,
            ],
        ]);
    }
}
