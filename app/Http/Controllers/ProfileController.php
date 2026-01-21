<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function updateProfile(Request $request)
    {
        try{
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $request->user()->id,
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $user = $request->user();

            if (isset($validatedData['name'])) {
                $user->name = $validatedData['name'];
            }
            if (isset($validatedData['email'])) {
                $user->email = $validatedData['email'];
            }

            if ($request->hasFile('avatar')) {

                $filename = Str::uuid() . '.webp';

                $image = Image::read($request->file('avatar'))
                    ->cover(300, 300)
                    ->toWebp(80);

                if ($user->avatar && Storage::disk('local')->exists('avatars/' . $user->avatar)) {
                    Storage::disk('local')->delete('avatars/' . $user->avatar);
                }

                Storage::disk('local')->put('avatars/' . $filename, (string) $image);

                $user->avatar = $filename;
            }

            $user->save();

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $user
            ]);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'An error occurred while updating the profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        try{
            $validatedData = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            $user = $request->user();

            if (!\Hash::check($validatedData['current_password'], $user->password)) {
                return response()->json(['message' => 'Current password is incorrect'], 400);
            }

            $user->password = $validatedData['new_password'];
            $user->save();

            return response()->json([
                'message' => 'Password changed successfully'
            ]);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'An error occurred while changing the password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
