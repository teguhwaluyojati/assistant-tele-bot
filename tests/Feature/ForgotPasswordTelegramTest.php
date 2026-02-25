<?php

namespace Tests\Feature;

use App\Models\PasswordResetCode;
use App\Models\TelegramUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Telegram\Bot\Laravel\Facades\Telegram;
use Tests\TestCase;

class ForgotPasswordTelegramTest extends TestCase
{
    use DatabaseTransactions;

    public function test_initiate_forgot_password_creates_reset_code_and_sends_telegram_message(): void
    {
        $telegramUser = TelegramUser::factory()->create();
        $user = User::factory()->create([
            'email' => 'forgot-flow@example.com',
            'telegram_user_id' => $telegramUser->id,
        ]);

        $fakeTelegram = new class {
            public array $payloads = [];

            public function sendMessage(array $payload): bool
            {
                $this->payloads[] = $payload;
                return true;
            }
        };

        Telegram::swap($fakeTelegram);

        $response = $this->postJson('/api/forgot-password/initiate', [
            'email' => $user->email,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'pending_verification');

        $record = PasswordResetCode::where('email', $user->email)->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->expires_at);
        $this->assertSame(0, $record->attempts);

        $this->assertCount(1, $fakeTelegram->payloads);
        $this->assertSame($telegramUser->user_id, $fakeTelegram->payloads[0]['chat_id']);
    }

    public function test_verify_forgot_password_resets_password_and_revokes_tokens(): void
    {
        $telegramUser = TelegramUser::factory()->create();
        $user = User::factory()->create([
            'email' => 'reset-success@example.com',
            'telegram_user_id' => $telegramUser->id,
            'password' => Hash::make('old-password'),
        ]);

        $oldToken = $user->createToken('old-token')->plainTextToken;
        $this->assertNotEmpty($oldToken);

        $fakeTelegram = new class {
            public array $payloads = [];

            public function sendMessage(array $payload): bool
            {
                $this->payloads[] = $payload;
                return true;
            }
        };

        Telegram::swap($fakeTelegram);

        $initiate = $this->postJson('/api/forgot-password/initiate', [
            'email' => $user->email,
        ]);

        $initiate->assertStatus(200);

        $this->assertNotEmpty($fakeTelegram->payloads);
        $message = (string) ($fakeTelegram->payloads[0]['text'] ?? '');
        preg_match('/`(\d{6})`/', $message, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $code = $matches[1];

        $response = $this->postJson('/api/forgot-password/verify', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertDatabaseMissing('password_reset_codes', [
            'email' => $user->email,
        ]);
        $this->assertCount(0, $user->tokens()->get());
    }
}
