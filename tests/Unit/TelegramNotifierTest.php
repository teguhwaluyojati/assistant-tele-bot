<?php

namespace Tests\Unit;

use App\Services\TelegramNotifier;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Telegram\Bot\Laravel\Facades\Telegram;
use Tests\TestCase;

class TelegramNotifierTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_notify_error_skips_when_chat_id_missing(): void
    {
        $original = getenv('TELEGRAM_ADMIN_ID');
        $fake = new class {
            public array $payloads = [];

            public function sendMessage(array $payload): bool
            {
                $this->payloads[] = $payload;

                return true;
            }
        };

        $this->setTelegramAdminId(null);
        Telegram::swap($fake);

        $notifier = new TelegramNotifier();
        $notifier->notifyError('Frontend Error', ['message' => 'oops']);

        $this->assertCount(0, $fake->payloads);

        $this->setTelegramAdminId($original === false ? null : $original);
        $this->resetTelegramFacade();
    }

    public function test_notify_error_sends_message(): void
    {
        $original = getenv('TELEGRAM_ADMIN_ID');
        $fake = new class {
            public array $payloads = [];

            public function sendMessage(array $payload): bool
            {
                $this->payloads[] = $payload;

                return true;
            }
        };

        $this->setTelegramAdminId('123');
        Telegram::swap($fake);

        $notifier = new TelegramNotifier();
        $notifier->notifyError('Frontend Error', ['message' => 'exploded']);

        $this->assertCount(1, $fake->payloads);
        $payload = $fake->payloads[0];
        $this->assertSame('123', $payload['chat_id']);
        $this->assertStringContainsString('Frontend Error', $payload['text']);
        $this->assertStringContainsString('message: exploded', $payload['text']);

        $this->setTelegramAdminId($original === false ? null : $original);
        $this->resetTelegramFacade();
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
