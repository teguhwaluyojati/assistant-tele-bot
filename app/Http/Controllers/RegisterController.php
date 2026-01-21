<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            ]);
            
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']), 
            ]);

            return response()->json([
                'message' => 'Registrasi berhasil!',
                'user' => $user
            ], 201); 

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal melakukan registrasi, silakan coba lagi.'
            ], 500); 
        }
    }
}
