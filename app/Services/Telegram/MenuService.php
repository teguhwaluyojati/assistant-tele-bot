<?php

namespace App\Services\Telegram;

use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class MenuService
{
    public function showMainMenu($chatId): void
    {
        $keyboard = [
            ['Cuaca di Jakarta 🌤️', 'Nasihat Bijak 💡'],
            ['Fakta Kucing 🐱', 'Money Tracker 💸'],
            ['Aku Mau Kopi ☕️', 'BSJP Saham 📊'],
            ['AI Chat 🤖', 'Swing Trade Saham 📊'],
            ['Tentang Developer 👨‍💻', 'Info Saham 📊'],
        ];

        $replyMarkup = Keyboard::make([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Hai! 👋 Silakan pilih salah satu menu di bawah ini:',
            'reply_markup' => $replyMarkup,
        ]);
    }
}
