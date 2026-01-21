<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LoginHistory;

class LoginController extends Controller
{
    public function login (Request $request)
    {
        try{
            $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required']
            ]);

            $credentials = $request->only('email', 'password');

            if (Auth::attempt($credentials)){
                $user = Auth::user();

                $token = $user->createToken('auth-token', ['*'], now()->addHours(2))->plainTextToken;

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
            } else {
                return response()->json([
                    'message' => 'Email atau password yang Anda masukkan salah.'
                ], 401);
            }

        }catch(\Illuminate\Validation\ValidationException $e){
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json(['message' => 'Logout berhasil!']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat logout.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
