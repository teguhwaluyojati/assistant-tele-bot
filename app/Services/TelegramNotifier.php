<?php

namespace App\Services;

use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

class TelegramNotifier
{
    public function notifyError(string $title, array $context = []): void
    {
        $chatId = trim((string) env('TELEGRAM_ADMIN_ID', ''));
        if ($chatId === '') {
            return;
        }

        $lines = [$title];

        foreach ($context as $key => $value) {
            $lines[] = $key . ': ' . $value;
        }

        $message = implode("\n", $lines);
        $message = $this->truncate($message, 3500);

        try {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
            ]);
        } catch (Throwable $e) {
            // Avoid recursive failures in the error notifier.
        }
    }

    private function truncate(string $message, int $maxLength): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 3) . '...';
    }
}
