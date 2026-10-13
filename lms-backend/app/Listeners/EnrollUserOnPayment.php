<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

class EnrollUserOnPayment
{
    /**
     * Setelah order dibayar, otomatis daftarkan user ke paket yang dibeli.
     * firstOrCreate memastikan tidak terjadi duplikasi jika event dikirim lebih dari sekali.
     */
    public function handle(OrderPaid $event): void
    {
        DB::transaction(function () use ($event) {
            Enrollment::firstOrCreate(
                [
                    'user_id' => $event->user->id,
                    'package_id' => $event->order->package_id,
                ],
                [
                    'order_id' => $event->order->id,
                    'progress_pct' => 0,
                    'enrolled_at' => now(),
                ]
            );
        });
    }
}
