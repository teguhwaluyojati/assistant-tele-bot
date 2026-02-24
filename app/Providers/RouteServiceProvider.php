<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $email = (string) $request->input('email', '');
            $key = strtolower($email) . '|' . $request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('auth-register-initiate', function (Request $request) {
            $telegramUsername = (string) $request->input('telegram_username', '');
            $key = strtolower($telegramUsername) . '|' . $request->ip();

            return Limit::perMinute(3)->by($key);
        });

        RateLimiter::for('auth-register-verify', function (Request $request) {
            $email = (string) $request->input('email', '');
            $key = strtolower($email) . '|' . $request->ip();

            return Limit::perMinute(6)->by($key);
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
