<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\TelegramUser;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

class RegisterController extends Controller
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

    public function initiateRegister(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:6'],
                'telegram_username' => ['required', 'string'],
            ]);

            $normalizedUsername = ltrim(trim($validatedData['telegram_username']), '@');
            
            // Find telegram user by username
            $telegramUser = TelegramUser::where('username', $normalizedUsername)
                ->first();
            
            if (!$telegramUser) {
                return response()->json([
                    'message' => 'Telegram account not found. Please start the bot first on Telegram, then try registering again.',
                    'status' => 'error'
                ], 404);
            }

            $isTelegramAlreadyUsed = User::where('telegram_user_id', $telegramUser->id)->exists();
            if ($isTelegramAlreadyUsed) {
                return response()->json([
                    'message' => 'This Telegram account is already linked to another web account and cannot be used to register again.',
                    'status' => 'error'
                ], 409);
            }
            
            // Generate 6-digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store verification record with 15 minute expiration
            $verification = VerificationCode::create([
                'email' => $validatedData['email'],
                'telegram_username' => $normalizedUsername,
                'code' => $code,
                'name' => $validatedData['name'],
                'password' => Hash::make($validatedData['password']),
                'expires_at' => now()->addMinutes(15),
            ]);
            
            // Send verification code via Telegram
            try {
                Telegram::sendMessage([
                    'chat_id' => $telegramUser->user_id,
                    'text' => "🔐 *Verification Code*\n\n" .
                             "Your verification code is: `{$code}`\n\n" .
                             "This code expires in 15 minutes.\n" .
                             "Do not share this code with anyone!",
                    'parse_mode' => 'Markdown',
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send verification code: ' . $e->getMessage());
                
                $verification->delete();

                $reason = $this->resolveTelegramSendErrorReason($e);
                
                return response()->json([
                    'message' => 'Failed to send verification code. ' . $reason,
                    'status' => 'error'
                ], 500);
            }
            
            return response()->json([
                'message' => 'Verification code sent to your Telegram. Enter the code to complete registration.',
                'status' => 'pending_verification',
                'email' => $validatedData['email'],
            ], 200);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
                'status' => 'error'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Register error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred during registration.',
                'status' => 'error'
            ], 500);
        }
    }
    
    public function verifyAndRegister(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'email' => ['required', 'string', 'email'],
                'code' => ['required', 'string', 'size:6'],
            ]);
            
            // Find verification record
            $verification = VerificationCode::where('email', $validatedData['email'])
                ->where('code', $validatedData['code'])
                ->first();
            
            if (!$verification) {
                return response()->json([
                    'message' => 'Invalid verification code.',
                    'status' => 'error'
                ], 401);
            }
            
            // Check if expired
            if ($verification->isExpired()) {
                $verification->delete();
                return response()->json([
                    'message' => 'Verification code has expired. Please register again.',
                    'status' => 'error'
                ], 401);
            }
            
            // Find telegram user and get their ID
            $telegramUser = TelegramUser::where('username', $verification->telegram_username)->first();
            if (!$telegramUser) {
                $verification->delete();
                return response()->json([
                    'message' => 'Telegram user not found.',
                    'status' => 'error'
                ], 404);
            }

            $isTelegramAlreadyUsed = User::where('telegram_user_id', $telegramUser->id)->exists();
            if ($isTelegramAlreadyUsed) {
                $verification->delete();
                return response()->json([
                    'message' => 'This Telegram account is already linked to another web account and cannot be used to register again.',
                    'status' => 'error'
                ], 409);
            }
            
            // Create user with telegram_user_id
            $user = User::create([
                'name' => $verification->name,
                'email' => $verification->email,
                'password' => $verification->password,
                'telegram_user_id' => $telegramUser->id,
            ]);
            
            // Create API token
            $token = $user->createToken('auth_token')->plainTextToken;

            activity()
                ->causedBy($user)
                ->withProperties([
                    'telegram_user_id' => $telegramUser->id,
                ])
                ->log('register');
            
            // Clean up verification record
            $verification->delete();
            
            // Send confirmation via Telegram (using already found telegramUser)
            try {
                Telegram::sendMessage([
                    'chat_id' => $telegramUser->user_id,
                    'text' => "✅ *Registration Successful!*\n\n" .
                             "Your account has been created successfully.\n" .
                             "You can now login to your dashboard.",
                    'parse_mode' => 'Markdown',
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send confirmation: ' . $e->getMessage());
            }
            
            return response()->json([
                'message' => 'Registration successful!',
                'user' => $user,
                'status' => 'success'
            ], 201)->cookie($this->buildAuthCookie($token));
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
                'status' => 'error'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Verify error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred during verification.',
                'status' => 'error'
            ], 500);
        }
    }

    private function resolveTelegramSendErrorReason(Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'unauthorized') || str_contains($message, 'invalid token')) {
            return 'Telegram bot token is invalid. Check TELEGRAM_BOT_TOKEN on Railway.';
        }

        if (str_contains($message, 'chat not found')) {
            return 'Telegram chat not found. User must open the bot and send /start first.';
        }

        if (str_contains($message, 'bot was blocked by the user')) {
            return 'Bot is blocked by user. Unblock the bot and send /start again.';
        }

        if (str_contains($message, 'forbidden')) {
            return 'Telegram rejected the request (forbidden). Ensure user has started the bot.';
        }

        if (str_contains($message, 'too many requests')) {
            return 'Too many requests to Telegram. Please retry in a moment.';
        }

        return 'Please verify TELEGRAM_BOT_TOKEN and make sure the user has started the bot.';
    }
}
