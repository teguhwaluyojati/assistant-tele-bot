<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController; // <-- PASTIKAN BARIS INI ADA


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

Route::get('/', function () {
    return view('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

Route::get('/style', function () {
    return view('style');
});

Route::get('/profile', function () {
    return view('profile');
});
