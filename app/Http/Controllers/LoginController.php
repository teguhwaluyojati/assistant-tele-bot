<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

            return response()->json([
                'message'       => 'Login berhasil!',
                'access_token'  => $token,
                'token_type'    => 'Bearer',
                'user'          => $user
            ]);
            
        }

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return response()->json([
                'message' => 'Login berhasil!',
                'user' => Auth::user()
            ], 200);
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
