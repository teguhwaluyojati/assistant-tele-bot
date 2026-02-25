<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClientErrorController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ForgotPasswordController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/user', function (Request $request) {
        return $request->user()->load('telegramUser');
    });
    
    Route::post('/logout', [LoginController::class, 'logout']);
    
    Route::get('/users', [DashboardController::class, 'getUsers']);
    Route::get('/users/{userId}', [DashboardController::class, 'getUserDetail']);
    Route::put('/users/{userId}/role', [DashboardController::class, 'updateUserRole']);
    Route::get('/users/me/commands', [DashboardController::class, 'getMyCommands']);
    Route::get('/dashboard/recent-commands', [DashboardController::class, 'getRecentCommands']);
    Route::get('/dashboard/recent-logins', [DashboardController::class, 'getRecentLogins']);

    Route::put('/update-profile', [ProfileController::class, 'updateProfile']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);

    Route::get('/history-login', [DashboardController::class, 'lastLogin']);
    
    Route::get('/transactions', [DashboardController::class, 'getTransactions']);
    Route::post('/transactions', [DashboardController::class, 'storeTransaction']);
    Route::get('/transactions/summary', [DashboardController::class, 'getTransactionsSummary']);
    Route::get('/transactions/daily-chart', [DashboardController::class, 'getDailyChart']);
    Route::get('/transactions/export', [DashboardController::class, 'exportTransactions']);
    Route::put('/transactions/{id}', [DashboardController::class, 'updateTransaction']);
    Route::delete('/transactions/{id}', [DashboardController::class, 'deleteTransaction']);
    Route::post('/transactions/bulk-delete', [DashboardController::class, 'bulkDeleteTransactions']);

    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/export', [AuditLogController::class, 'export']);
});



Route::post('/webhook', [TelegramController::class, 'handle']);

Route::get('/daily-expenses', [TelegramController::class, 'broadcastDailyExpenses']);

//Login Register Logout
Route::post('/register/initiate', [RegisterController::class, 'initiateRegister'])
    ->middleware('throttle:auth-register-initiate');
Route::post('/register/verify', [RegisterController::class, 'verifyAndRegister'])
    ->middleware('throttle:auth-register-verify');
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:auth-login');
Route::post('/forgot-password/initiate', [ForgotPasswordController::class, 'initiate'])
    ->middleware('throttle:auth-forgot-initiate');
Route::post('/forgot-password/verify', [ForgotPasswordController::class, 'verify'])
    ->middleware('throttle:auth-forgot-verify');

Route::post('/client-error', [ClientErrorController::class, 'store'])
    ->middleware('throttle:client-error');

Route::post('/stocks/upload', [DashboardController::class, 'upload']);
