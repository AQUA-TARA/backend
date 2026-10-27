<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('reports', function (Request $request) {
            // Rate limit report submission: 10 requests per minute per IP or authenticated user
            return Limit::perMinute(10)->by($request->user('citizen')?->id ?: $request->ip());
        });
    }
}
