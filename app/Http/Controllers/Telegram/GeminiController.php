<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramUser;
use App\Services\Telegram\MenuService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class GeminiController extends Controller
{
    private MenuService $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    public function handleGeminiChatMode(TelegramUser $user, $update): void
    {
        $chatId = $update['message']['chat']['id'];

        if ($update->getMessage() && $update->getMessage()->has('text')) {
            $text = $update->getMessage()->getText();

            \App\Models\TelegramUserCommand::create([
                'user_id' => $chatId,
                'command' => 'AI: ' . $text,
            ]);

            if ($text === '/selesai' || strtolower($text) === 'selesai') {
                $this->exitGeminiChatMode($user, $chatId);
            } else {
                $this->askGemini($chatId, $text);
            }
        } elseif ($update->isType('callback_query')) {
            $callbackQuery = $update->getCallbackQuery();
            Telegram::answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Anda sedang dalam mode chat AI. Ketik /selesai untuk keluar.',
            ]);
        } else {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Silakan kirim pesan teks untuk berinteraksi dengan AI. Ketik /selesai untuk keluar dari mode ini.',
            ]);
        }
    }

    public function enterGeminiChatMode(TelegramUser $user, $chatId): void
    {
        $user->state = 'gemini_chat';
        $user->save();

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "🤖 Anda sekarang dalam mode chat dengan AI Gemini.\n\nSilakan ajukan pertanyaan apa pun.\nKetik `/selesai` untuk keluar dari mode ini (Jika tidak ada response dalam 5 menit, maka akan otomatis keluar dari mode AI).",
        ]);
    }

    public function exitGeminiChatMode(TelegramUser $user, $chatId): void
    {
        $user->state = 'normal';
        $user->save();

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '✅ Keluar/selesai berhasil. Kembali ke menu utama.',
        ]);

        $this->menuService->showMainMenu($chatId);
    }

    public function askGemini($chatId, $update): void
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            Log::error('GEMINI_API_KEY is not set in .env');
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Maaf, layanan AI sedang tidak terkonfigurasi.',
            ]);
            return;
        }

        Telegram::sendChatAction(['chat_id' => $chatId, 'action' => 'typing']);

        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;

        try {
            $response = Http::timeout(60)->post($apiUrl, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $update],
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                if (!empty($response->json()['candidates'])) {
                    $reply = $response->json()['candidates'][0]['content']['parts'][0]['text'];
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $reply,
                        'parse_mode' => 'Markdown',
                    ]);
                } else {
                    Log::warning('Gemini API call successful but no candidates returned. Prompt may be blocked.');
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Maaf, pertanyaan Anda tidak dapat diproses saat ini. Coba dengan pertanyaan lain.',
                    ]);
                }
            } else {
                Log::error('Gemini API Error: ' . $response->body());
                if ($response->status() == 429) {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => '🚧 Maaf, layanan AI Chat sedang sibuk karena telah mencapai limit. Silakan coba lagi nanti. 🙏',
                    ]);
                } else {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Maaf, terjadi kesalahan saat menghubungi layanan AI.',
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Exception during Gemini API call: ' . $e->getMessage());
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Halo, ada yang bisa saya bantu?'
            ]);
        }
    }
}
