<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Log;
use Telegram\Bot\Exceptions\TelegramOtherException;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramMessageService
{
    public function sendMessageSafely(array $params): void
    {
        try {
            Telegram::sendMessage($params);
        } catch (TelegramOtherException $e) {
            if (str_contains($e->getMessage(), 'chat not found')) {
                $chatId = $params['chat_id'] ?? 'N/A';
                Log::warning("Gagal kirim pesan: Chat not found untuk chatId: {$chatId}. Kemungkinan user memblokir bot.");
            } else {
                $chatId = $params['chat_id'] ?? 'N/A';
                Log::error("Telegram API Error ke chatId {$chatId}: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            $chatId = $params['chat_id'] ?? 'N/A';
            Log::error("Terjadi error umum saat mengirim pesan ke chatId: {$chatId}", ['exception' => $e]);
        }
    }

    public function escapeMarkdown(string $text): string
    {
        $escapeChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

        return str_replace($escapeChars, array_map(function ($char) {
            return '\\' . $char;
        }, $escapeChars), $text);
    }
}
