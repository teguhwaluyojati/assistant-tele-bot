<?php

namespace Tests\Feature;

use App\Models\TelegramUser;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Telegram\Bot\Laravel\Facades\Telegram;
use Tests\TestCase;

class RegisterFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_initiate_register_sends_code_and_creates_verification(): void
    {
        $original = getenv('TELEGRAM_ADMIN_ID');
        $fake = $this->makeTelegramFake();
        Telegram::swap($fake);

        $telegramUser = TelegramUser::factory()->create([
            'username' => 'john_doe',
        ]);

        $response = $this->postJson('/api/register/initiate', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
            'telegram_username' => '@john_doe',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'pending_verification')
            ->assertJsonPath('email', 'john@example.com');

        $this->assertDatabaseHas('verification_codes', [
            'email' => 'john@example.com',
            'telegram_username' => 'john_doe',
            'name' => 'John Doe',
        ]);

        $this->assertCount(1, $fake->payloads);
        $this->assertSame($telegramUser->user_id, $fake->payloads[0]['chat_id']);

        $this->setTelegramAdminId($original === false ? null : $original);
        $this->resetTelegramFacade();
    }

    public function test_initiate_register_returns_404_when_telegram_user_missing(): void
    {
        $response = $this->postJson('/api/register/initiate', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
            'telegram_username' => '@missing_user',
        ]);

        $response
            ->assertStatus(404)
            ->assertJsonPath('status', 'error');
    }

    public function test_initiate_register_returns_409_when_telegram_already_linked(): void
    {
        $telegramUser = TelegramUser::factory()->create([
            'username' => 'already_linked',
        ]);
        User::factory()->create([
            'telegram_user_id' => $telegramUser->id,
        ]);

        $response = $this->postJson('/api/register/initiate', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
            'telegram_username' => '@already_linked',
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('status', 'error');
    }

    public function test_verify_register_creates_user_and_deletes_verification(): void
    {
        $original = getenv('TELEGRAM_ADMIN_ID');
        $fake = $this->makeTelegramFake();
        Telegram::swap($fake);

        $telegramUser = TelegramUser::factory()->create([
            'username' => 'verify_user',
        ]);

        VerificationCode::create([
            'email' => 'verify@example.com',
            'telegram_username' => 'verify_user',
            'code' => '123456',
            'name' => 'Verify User',
            'password' => Hash::make('secret123'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/register/verify', [
            'email' => 'verify@example.com',
            'code' => '123456',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertCookie('auth_token');

        $this->assertDatabaseHas('users', [
            'email' => 'verify@example.com',
            'telegram_user_id' => $telegramUser->id,
        ]);

        $this->assertDatabaseMissing('verification_codes', [
            'email' => 'verify@example.com',
        ]);

        $this->assertCount(1, $fake->payloads);

        $this->setTelegramAdminId($original === false ? null : $original);
        $this->resetTelegramFacade();
    }

    public function test_verify_register_rejects_expired_code(): void
    {
        TelegramUser::factory()->create([
            'username' => 'expired_user',
        ]);

        VerificationCode::create([
            'email' => 'expired@example.com',
            'telegram_username' => 'expired_user',
            'code' => '654321',
            'name' => 'Expired User',
            'password' => Hash::make('secret123'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/register/verify', [
            'email' => 'expired@example.com',
            'code' => '654321',
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseMissing('verification_codes', [
            'email' => 'expired@example.com',
        ]);
    }

    private function makeTelegramFake(): object
    {
        return new class {
            public array $payloads = [];

            public function sendMessage(array $payload): bool
            {
                $this->payloads[] = $payload;

                return true;
            }
        };
    }

    private function setTelegramAdminId(?string $value): void
    {
        if ($value === null || $value === '') {
            putenv('TELEGRAM_ADMIN_ID');
            unset($_ENV['TELEGRAM_ADMIN_ID'], $_SERVER['TELEGRAM_ADMIN_ID']);
            return;
        }

        putenv("TELEGRAM_ADMIN_ID={$value}");
        $_ENV['TELEGRAM_ADMIN_ID'] = $value;
        $_SERVER['TELEGRAM_ADMIN_ID'] = $value;
    }

    private function resetTelegramFacade(): void
    {
        Telegram::swap(app(\Telegram\Bot\BotsManager::class));
    }
}
