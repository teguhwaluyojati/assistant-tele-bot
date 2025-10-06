<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LoginHistory;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $credentials = $request->only('email', 'password');

            if (Auth::attempt($credentials)) {

            $user = Auth::user();

            $token = $user->createToken('auth-token')->plainTextToken;

            LoginHistory::create([
                'email' => $request->email,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message'       => 'Login berhasil!',
                'access_token'  => $token,
                'token_type'    => 'Bearer',
                'user'          => $user
            ]);
            
        }

        return response()->json([
            'message' => 'Email atau password yang Anda masukkan salah.'
        ], 401); 
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil!']);
    }
}
