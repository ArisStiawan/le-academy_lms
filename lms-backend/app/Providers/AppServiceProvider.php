<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiter login: maksimal 10 percobaan / menit per kombinasi email + IP.
        // Melindungi dari brute-force tanpa mengganggu pemakaian normal.
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(10)->by($key);
        });

        // Rate limiter register: maksimal 20 akun / menit per IP (anti spam akun).
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
