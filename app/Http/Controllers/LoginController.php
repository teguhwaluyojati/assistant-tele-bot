<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LoginHistory;
use Laravel\Sanctum\PersonalAccessToken;

class LoginController extends Controller
{
    private function buildAuthCookie(string $token)
    {
        return cookie(
            'auth_token',
            $token,
            120,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            'lax'
        );
    }

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

                activity()
                    ->causedBy($user)
                    ->withProperties([
                        'ip' => $request->ip(),
                    ])
                    ->log('login');

                return response()->json([
                    'message'       => 'Login berhasil!',
                    'user'          => $user
                ])->cookie($this->buildAuthCookie($token));
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
            $user = $request->user();
            $plainToken = $request->cookie('auth_token');
            if (!empty($plainToken)) {
                $tokenModel = PersonalAccessToken::findToken($plainToken);
                if ($tokenModel) {
                    $tokenModel->delete();
                }
            }

            if ($user) {
                activity()
                    ->causedBy($user)
                    ->withProperties([
                        'ip' => $request->ip(),
                    ])
                    ->log('logout');
            }

            return response()->json(['message' => 'Logout berhasil!'])
                ->cookie(cookie()->forget('auth_token'));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat logout.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
