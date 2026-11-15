<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);
        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(30)->by('login-ip:' . $request->ip()),
            Limit::perMinute(5)->by('login-account:' . hash('sha256', strtolower(trim((string) $request->input('email'))) . '|' . $request->ip())),
        ]);
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(5)->by('register:' . $request->ip()));
    }
}
