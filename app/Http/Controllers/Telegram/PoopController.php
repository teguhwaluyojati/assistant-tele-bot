<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\PoopTracker;
use App\Services\Telegram\TelegramMessageService;

class PoopController extends Controller
{
    private TelegramMessageService $messageService;

    public function __construct(TelegramMessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    public function sendPoopTrackerInfo($chatId): void
    {
        $message = "💩 *Poop Tracker*\n\n";
        $message .= "Fitur ini akan membantu Anda melacak kebiasaan buang air besar Anda. Anda dapat mencatat waktu, konsistensi, dan catatan tambahan.\n\n";
        $message .= "Untuk memulai, kirim pesan dengan format berikut:\n";
        $message .= "`/poop [konsistensi] [catatan]`\n";
        $message .= "Contoh: `/poop Normal Perasaan baik hari ini`\n\n";
        $message .= "Konsistensi yang umum digunakan: Cair, Lunak, Normal, Keras, Sangat Keras.\n\n";
        $message .= "Untuk melihat riwayat poop Anda, ketik `/poophistory`.\n\n";
        $message .= "Catatan: Fitur ini masih dalam pengembangan. Nantikan pembaruan selanjutnya!";

        $this->messageService->sendMessageSafely([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }

    public function handlePoopCommand($chatId, $text): void
    {
        $pattern = '/^\/poop\s+(\w+)(?:\s+(.*))?$/i';
        $historyPattern = '/^\/poophistory$/i';

        if (preg_match($pattern, $text, $matches)) {
            $type = $matches[1];
            $notes = isset($matches[2]) ? trim($matches[2]) : null;

            PoopTracker::create([
                'user_id' => $chatId,
                'type' => $type,
                'notes' => $notes,
            ]);

            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => "✅ Catatan poop berhasil disimpan!\nTipe: *{$type}*\nCatatan: " . ($notes ? "*{$notes}*" : '_(tidak ada catatan)_'),
                'parse_mode' => 'Markdown',
            ]);
        } elseif (preg_match($historyPattern, $text)) {
            $records = PoopTracker::where('user_id', $chatId)->latest()->take(10)->get();

            if ($records->isEmpty()) {
                $this->messageService->sendMessageSafely([
                    'chat_id' => $chatId,
                    'text' => 'Anda belum memiliki catatan poop.',
                ]);
                return;
            }

            $message = "📋 *Riwayat Poop Terakhir:*\n\n";
            foreach ($records as $record) {
                $date = $record->created_at->format('d M Y H:i');
                $message .= "▫️ {$date} | Tipe: *{$record->type}*";
                if ($record->notes) {
                    $message .= " | Catatan: _{$record->notes}_";
                }
                $message .= "\n";
            }

            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);
        } else {
            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => 'Format salah. Gunakan `/poop [konsistensi] [catatan]` untuk mencatat poop atau `/poophistory` untuk melihat riwayat.',
            ]);
        }
    }
}
