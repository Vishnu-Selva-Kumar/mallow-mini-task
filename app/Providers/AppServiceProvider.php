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
        RateLimiter::for('usage-metering', function (Request $request) {
            $identifier = $request->header('X-API-Key')
                ?? $request->input('user_id')
                ?? $request->ip();

            return Limit::perMinute(120)->by($identifier);
        });
    }
}
