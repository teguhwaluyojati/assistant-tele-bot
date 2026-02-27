<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\UserController;
use App\Models\PageVisit;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function (Request $request) {
    try {
        $resolveClientIp = static function (Request $request): string {
            $candidates = [];

            $forwardedFor = (string) $request->header('x-forwarded-for', '');
            if ($forwardedFor !== '') {
                $candidates = array_merge($candidates, array_map('trim', explode(',', $forwardedFor)));
            }

            $candidates[] = (string) $request->header('cf-connecting-ip', '');
            $candidates[] = (string) $request->header('x-real-ip', '');
            $candidates[] = (string) $request->ip();

            foreach ($candidates as $ip) {
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }

            return '0.0.0.0';
        };

        $ipAddress = $resolveClientIp($request);
        $userAgent = mb_substr((string) $request->userAgent(), 0, 1000);
        $userAgentHash = $userAgent !== '' ? hash('sha256', $userAgent) : null;
        $windowStart = now()->subMinutes(30);

        $existingVisit = PageVisit::query()
            ->where('path', '/')
            ->where('ip_address', $ipAddress)
            ->where('user_agent_hash', $userAgentHash)
            ->where('last_seen_at', '>=', $windowStart)
            ->latest('last_seen_at')
            ->first();

        if ($existingVisit) {
            $existingVisit->hit_count = $existingVisit->hit_count + 1;
            $existingVisit->last_seen_at = now();
            $existingVisit->save();
        } else {
            PageVisit::create([
                'path' => '/',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'user_agent_hash' => $userAgentHash,
                'hit_count' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }
    } catch (\Throwable $e) {
        Log::warning('Failed to record root page visit: ' . $e->getMessage());
    }

    return view('login');
});

Route::get('/avatar/{filename}', [UserController::class, 'showAvatar'])
    ->middleware('signed')
    ->name('avatar.show');

Route::get('/login', function () {
    return view('login');
});

Route::get('/register', function () {
    return view('register');
});

Route::get('/forgot-password', function () {
    return view('forgot-password');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

Route::get('/audit-logs', function () {
    return view('audit-logs');
});

Route::get('/transactions', function () {
    return view('transactions');
});

Route::get('/users', function () {
    return view('users');
});

Route::get('/style', function () {
    return view('style');
});

Route::get('/profile', function () {
    return view('profile');
});
