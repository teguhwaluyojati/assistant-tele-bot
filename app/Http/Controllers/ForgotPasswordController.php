<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class ForgotPasswordController extends Controller
{
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::with('telegramUser')->where('email', $email)->first();

        if (!$user || !$user->telegramUser) {
            return response()->json([
                'status' => 'ok',
                'message' => 'If your account exists, a verification code has been sent to your Telegram.',
            ]);
        }

        $telegramUsername = $user->telegramUser->username;
        $chatId = $user->telegramUser->user_id;
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordResetCode::updateOrCreate(
            ['email' => $email],
            [
                'telegram_username' => $telegramUsername,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(15),
            ]
        );

        try {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "🔐 *Password Reset Code*\n\n"
                    . "Your password reset code is: `{$code}`\n\n"
                    . "This code will expire in 15 minutes.\n"
                    . "Do not share this code with anyone.\n\n"
                    . "If you did not request a password reset, please ignore this message.",
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send forgot-password code: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send verification code. Please try again.',
            ], 500);
        }

        return response()->json([
            'status' => 'pending_verification',
            'message' => 'Verification code sent to your Telegram.',
            'email' => $email,
        ]);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $email = strtolower(trim($validated['email']));
        $resetCode = PasswordResetCode::where('email', $email)->first();

        if (!$resetCode) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired verification code.',
            ], 401);
        }

        if ($resetCode->isExpired()) {
            $resetCode->delete();

            return response()->json([
                'status' => 'error',
                'message' => 'Verification code has expired. Please request a new code.',
            ], 401);
        }

        if (!Hash::check($validated['code'], $resetCode->code_hash)) {
            $resetCode->attempts = $resetCode->attempts + 1;
            $resetCode->save();

            if ($resetCode->attempts >= 5) {
                $resetCode->delete();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Too many invalid attempts. Please request a new code.',
                ], 429);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid verification code.',
            ], 401);
        }

        $user = User::with('telegramUser')->where('email', $email)->first();
        if (!$user) {
            $resetCode->delete();

            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
            ], 404);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();
        $user->tokens()->delete();

        activity()
            ->causedBy($user)
            ->withProperties([
                'ip' => $request->ip(),
            ])
            ->log('password_reset');

        try {
            if ($user->telegramUser) {
                Telegram::sendMessage([
                    'chat_id' => $user->telegramUser->user_id,
                    'text' => "✅ *Password Reset Successful*\n\n"
                        . "Your web account password has been updated.",
                    'parse_mode' => 'Markdown',
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to send password reset confirmation: ' . $e->getMessage());
        }

        $resetCode->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been reset successfully. Please login with your new password.',
        ]);
    }
}
