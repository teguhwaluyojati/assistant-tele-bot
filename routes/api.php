<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;

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
        return $request->user();
    });
    
    Route::post('/logout', [LoginController::class, 'logout']);
    
    Route::get('/users', [DashboardController::class, 'getUsers']);

    Route::put('/update-profile', [ProfileController::class, 'updateProfile']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);

    Route::get('/history-login', [DashboardController::class, 'lastLogin']);
    
    Route::get('/transactions', [DashboardController::class, 'getTransactions']);
    Route::get('/transactions/summary', [DashboardController::class, 'getTransactionsSummary']);
});



Route::post('/webhook', [TelegramController::class, 'handle']);

Route::get('/daily-expenses', [TelegramController::class, 'broadcastDailyExpenses']);

//Login Register Logout
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

Route::post('/stocks/upload', [DashboardController::class, 'upload']);
