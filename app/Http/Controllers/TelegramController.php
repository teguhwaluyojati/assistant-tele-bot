<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Telegram\Bot\FileUpload\InputFile;
use App\Models\TelegramUserCommand;
use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Models\PoopTracker;
use Illuminate\Support\Facades\DB;


class TelegramController extends Controller
{
    private $transaction;

    public function __construct()
    {
        $this->transaction = new Transaction();
    }

    /**
     * Handle incoming Telegram updates.
     */
    public function handle()
    {
        date_default_timezone_set('Asia/Jakarta');
        $update = Telegram::getWebhookUpdate();

        $user = $this->findOrCreateUser($update);

        if (!$user) {
            Log::warning('Request diabaikan: Gagal mendapatkan data user/chatId.');
            return response()->json(['ok' => true]);
        }

        if ($user->state === 'gemini_chat' && $user->last_interaction_at && now()->diffInMinutes($user->last_interaction_at->setTimezone('Asia/Jakarta')) > 5) {
            
            Log::info("User {$user->user_id} di-timeout dari mode Gemini.");
            $this->exitGeminiChatMode($user, true); 
            return response()->json(['ok' => true]);
        }

        $this->updateLastInteraction($user);

        if ($user->state !== 'normal') {
            $this->handleStatefulInput($user, $update);
        } else {
            $this->handleNormalMode($user, $update);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Mencari atau membuat pengguna baru TANPA mengubah timestamp interaksi.
     * @param \Telegram\Bot\Objects\Update $update
     * @return \App\Models\TelegramUser|null
     */
    private function findOrCreateUser($update)
    {
        try {
            $from = $update->isType('callback_query') ? $update->getCallbackQuery()->getFrom() : $update->getMessage()->getFrom();
            if (!$from) return null;

            return \App\Models\TelegramUser::firstOrCreate(
                ['user_id' => $from->getId()], // Gunakan user_id sebagai primary key
                [
                    'username'   => $from->getUsername(),
                    'first_name' => $from->getFirstName(),
                    'last_name'  => $from->getLastName(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Gagal findOrCreateUser: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Memperbarui timestamp interaksi terakhir untuk pengguna.
     * @param \App\Models\TelegramUser $user
     */
    private function updateLastInteraction(\App\Models\TelegramUser $user)
    {
        $user->last_interaction_at = now();
        $user->save();
    }

    // ===================================================================
    // HANDLER UNTUK MODE STATEFUL (Saat User "Sibuk")
    // ===================================================================
    private function handleStatefulInput(TelegramUser $user, $update)
    {
        if ($user->state === 'gemini_chat') {
            $this->handleGeminiChatMode($user, $update);
        }else if ($user->state === 'editing_options') {
            $this->handleEditingChatMode($user, $update);
        }else if (str_starts_with($user->state, 'editing_')) {
        $this->handleEditTransactionInput($user, $update->getMessage()->getText());
        }
    }

    /**
     * Memulai mode edit dan menampilkan daftar transaksi untuk dipilih.
     */
    private function startEditMode(\App\Models\TelegramUser $user)
    {

        $transactions = \App\Models\Transaction::where('user_id', $user->user_id)
            ->latest()
            ->get();

        if ($transactions->isEmpty()) {
            $this->sendMessageSafely([
                'chat_id' => $user->user_id,
                'text' => 'Anda belum memiliki transaksi untuk diedit.'
            ]);
            return;
        }

        $message = "Pilih transaksi yang ingin Anda edit:\n\n";
        $inlineKeyboard = [];

        foreach ($transactions as $transaction) {
            $type = $transaction->type === 'income' ? '+' : '-';
            $amount = number_format($transaction->amount);
            $date = $transaction->created_at->format('d M');

            $message .= "🆔 *{$transaction->id}* | {$date} | `{$type} Rp {$amount}`\n";
            $message .= "_{$this->escapeMarkdown($transaction->description)}_\n\n";

            $inlineKeyboard[] = [
                Keyboard::inlineButton([
                    'text' => "Pilih Transaksi ID: {$transaction->id}",
                    'callback_data' => 'select_edit_trx_' . $transaction->id 
                ])
            ];
        }
        
        $inlineKeyboard[] = [Keyboard::inlineButton(['text' => 'Batalkan', 'callback_data' => 'cancel_generic'])];

        $this->sendMessageSafely([
            'chat_id' => $user->user_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard])
        ]);
    }

    /**
     * Menampilkan pilihan edit (Jumlah, Deskripsi, Tanggal) untuk transaksi yang dipilih.
     *
     * @param \App\Models\TelegramUser $user
     * @param int $messageId ID pesan yang akan diedit
     * @param int $transactionId ID transaksi yang sedang diedit
     */
    private function showEditOptions(\App\Models\TelegramUser $user, $messageId, $transactionId)
    {
        $transaction = \App\Models\Transaction::find($transactionId);

        if (!$transaction) {
            $this->sendMessageSafely([
                'chat_id' => $user->user_id,
                'text' => 'Error: Transaksi tidak ditemukan lagi.'
            ]);
            return;
        }

        $inlineKeyboard = [
            [
                Keyboard::inlineButton(['text' => '✏️ Ubah Jumlah', 'callback_data' => 'edit_field_amount_' . $transactionId]),
                Keyboard::inlineButton(['text' => '📝 Ubah Deskripsi', 'callback_data' => 'edit_field_description_' . $transactionId]),
            ],
            [
                Keyboard::inlineButton(['text' => '🗓️ Ubah Tanggal', 'callback_data' => 'edit_field_date_' . $transactionId]),
            ],
            [
                Keyboard::inlineButton(['text' => '⬅️ Batal & Kembali ke Menu', 'callback_data' => 'cancel_edit_full']),
            ]
        ];
        
        $type = $transaction->type === 'income' ? 'Pemasukan' : 'Pengeluaran';
        $amount = number_format($transaction->amount);
        $date = $transaction->created_at->format('d M Y H:i');

        $message = "Anda akan mengedit Transaksi ID: *{$transactionId}*\n\n";
        $message .= "*Tipe:* {$type}\n";
        $message .= "*Jumlah:* Rp {$amount}\n";
        $message .= "*Tanggal:* {$date}\n";
        $message .= "*Deskripsi:* {$transaction->description}\n\n";
        $message .= "Apa yang ingin Anda ubah?";

        try {
            Telegram::editMessageText([
                'chat_id'      => $user->user_id,
                'message_id'   => $messageId,
                'text'         => $message,
                'parse_mode'   => 'Markdown',
                'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard])
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal menampilkan opsi edit: ' . $e->getMessage());
            $this->sendMessageSafely(['chat_id' => $user->user_id, 'text' => 'Silakan pilih field yang ingin diedit:']);
        }
    }

    private function handleEditingChatMode(TelegramUser $user, $update)
    {
        if (!$update->isType('callback_query')) {
            return; 
        }

        $callbackQuery = $update->getCallbackQuery();
        $data = $callbackQuery->getData();
        \App\Models\TelegramUserCommand::create([
            'user_id' => $user->user_id,
            'command' => "CALLBACK: " . $data
        ]);

        if (str_starts_with($data, 'edit_field_')) {
            [, , $field, $transactionId] = explode('_', $data);
            
            $this->promptForNewValue($user, $field, $transactionId);

        } else if ($data === 'cancel_edit_full') {
            $user->state = 'normal';
            $user->save();
            Telegram::editMessageText([
                'chat_id'      => $user->user_id,
                'message_id'   => $callbackQuery->getMessage()->getMessageId(),
                'text'         => 'Proses edit dibatalkan.',
                'reply_markup' => null
            ]);
        }
    }

    /**
     * Meminta input baru dari pengguna dan mengubah state.
     * Dipanggil setelah pengguna memilih field (jumlah/deskripsi/tanggal) yang akan diedit.
     */
    private function promptForNewValue(\App\Models\TelegramUser $user, $field, $transactionId)
    {
        $user->state = "editing_{$field}_{$transactionId}";
        $user->save();

        $promptMessage = "";
        switch ($field) {
            case 'amount':
                $promptMessage = "Silakan masukkan *jumlah* baru untuk transaksi ini (hanya angka).";
                break;
            case 'description':
                $promptMessage = "Silakan masukkan *deskripsi* baru untuk transaksi ini.";
                break;
            case 'date':
                $promptMessage = "Silakan masukkan *tanggal* baru dengan format YYYY-MM-DD (Contoh: `2025-09-10`).";
                break;
        }
        
        $promptMessage .= "\n\nKetik `/batal` untuk membatalkan proses edit ini.";

        $this->sendMessageSafely([
            'chat_id' => $user->user_id,
            'text' => $promptMessage,
            'parse_mode' => 'Markdown'
        ]);
    }

    /**
     * Memproses input teks dari pengguna saat dalam mode edit transaksi.
     */
    private function handleEditTransactionInput(\App\Models\TelegramUser $user, $newValue)
    {
        $chatId = $user->user_id;

        if (strtolower($newValue) === '/batal') {
            $user->state = 'normal';
            $user->save();
            $this->sendMessageSafely(['chat_id' => $chatId, 'text' => '✅ Proses edit dibatalkan.']);
            return;
        }

        [, $field, $transactionId] = explode('_', $user->state);
        
        $transaction = \App\Models\Transaction::where('id', $transactionId)->where('user_id', $chatId)->first();
        if (!$transaction) {
            $this->sendMessageSafely(['chat_id' => $chatId, 'text' => '⚠️ Gagal memperbarui, transaksi tidak ditemukan.']);
            $user->state = 'normal';
            $user->save();
            return;
        }

        if ($field === 'amount' && !is_numeric($newValue)) {
            $this->sendMessageSafely(['chat_id' => $chatId, 'text' => '❌ Jumlah harus berupa angka. Silakan coba lagi.']);
            return;
        }

        $transaction->{$field} = $newValue;
        $transaction->save();

        $user->state = 'normal';
        $user->save();
        $this->sendMessageSafely(['chat_id' => $chatId, 'text' => "✅ Transaksi ID {$transactionId} berhasil diperbarui!"]);
    }

    private function handleGeminiChatMode(TelegramUser $user, $update)
    {
        $chatId = $user->user_id;

        if ($update->getMessage() && $update->getMessage()->has('text')) {
            $text = $update->getMessage()->getText();

            \App\Models\TelegramUserCommand::create([
                'user_id' => $chatId,
                'command' => 'AI: '. $text
            ]);

            if ($text === '/selesai' || strtolower($text) === 'selesai') {
                $this->exitGeminiChatMode($user, $chatId);
            } else {
                $this->askGemini($chatId, $text);
            }
        } else if ($update->isType('callback_query')) {
            $callbackQuery = $update->getCallbackQuery();
            Telegram::answerCallbackQuery(['callback_query_id' => $callbackQuery->getId(), 'text' => 'Anda sedang dalam mode chat AI. Ketik /selesai untuk keluar.']);
        } else {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Silakan kirim pesan teks untuk berinteraksi dengan AI. Ketik /selesai untuk keluar dari mode ini.']);
        }
    }

    // ===================================================================
    // HANDLER UNTUK MODE NORMAL (Menu & Perintah Biasa)
    // ===================================================================
    private function handleNormalMode(TelegramUser $user, $update)
    {
        $chatId = $user->user_id;

        if ($update->isType('callback_query')) {
            $callbackQuery = $update->getCallbackQuery();
            $messageId = $callbackQuery->getMessage()->getMessageId();
            $data = $callbackQuery->getData();

            Telegram::answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);
            \App\Models\TelegramUserCommand::create([
                'user_id' => $chatId,
                'command' => "CALLBACK: " . $data
            ]);

            if (str_starts_with($data, 'genshin_page_')) {
            list($_, $_, $category, $page) = explode('_', $data, 4);
            $this->showItemsInCategory($chatId, $messageId, $category, (int)$page);
            }else if (str_starts_with($data, 'category_')) {
            $category = substr($data, 9);
            $this->showItemsInCategory($chatId, $messageId, $category, 1);             
            } else if (str_starts_with($data, 'item_')) {
            list($_, $category, $itemName) = explode('_', $data, 3);
            $this->showItemDetails($chatId, $messageId, $category, $itemName);
            }else if ($data === 'back_to_categories') {
            $this->showGenshinCategories($chatId, $messageId);
            }else if (str_starts_with($data, 'delete_trx_')) {
                $transactionId = substr($data, 11);
                $transaction = \App\Models\Transaction::where('user_id', $chatId)->where('id', $transactionId)->first();
                if ($transaction) {
                    $deletedDescription = $transaction->description;
                    $transaction->delete();
                     Telegram::editMessageText([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => "✅ Transaksi '{$deletedDescription}' (ID: {$transactionId}) berhasil dihapus",
                        'reply_markup'=> null
                    ]);
                } else {
                    Telegram::editMessageText([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => "⚠️ Transaksi tidak dapat ditemukan atau sudah dihapus.",
                        'reply_markup' => null
                    ]);
                }
            }
            else if (str_starts_with($data, 'select_edit_trx_')) {
        $user->state = 'editing_options'; 
        $user->save();
        
        $transactionId = substr($data, 16);
        
        $this->showEditOptions($user, $messageId, $transactionId);
    }
    else if ($data === 'cancel_generic') {
        Telegram::editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'Proses dibatalkan.',
            'reply_markup' => null
        ]);
    }
             else if (str_starts_with($data, 'summary_')) {
                $period = substr($data, 8); 
                $this->generateSummary($chatId, $messageId, $period);            
            }

        } else if ($update->getMessage() && $update->getMessage()->has('text')) {
            $text = $update->getMessage()->getText();
            \App\Models\TelegramUserCommand::create([
                'user_id' => $chatId,
                'command' => $text
            ]);

            if (str_starts_with($text, '/')) {
                if ($text === '/start' || $text === '/menu') { $this->showMainMenu($chatId); }
                else if ($text === '/summary' || $text === '/laporan') { $this->showSummaryOptions($chatId); }
                else if ($text === '/hapus') { $this->showRecentTransactionsForDeletion($chatId); }
                else if ($text === '/edit') { $this->startEditMode($user); }
                else if (str_starts_with($text, '/poop') || (str_starts_with($text, '/poophistory'))) { $this->handlePoopCommand($chatId, $text); }
                else { $this->handleAdminCommands($chatId, $text); }
            } else if (str_starts_with($text, '+') || str_starts_with($text, '-')) {
                $this->recordTransaction($chatId, $text);
            } else {
                switch ($text) {
                    case 'AI Chat 🤖': $this->enterGeminiChatMode($user, $chatId); break;
                    case 'Cuaca di Jakarta 🌤️': $this->sendWeatherInfo($chatId); break;
                    case 'Nasihat Bijak 💡': $this->sendAdvice($chatId); break;
                    case 'Fakta Kucing 🐱': $this->sendCatFact($chatId); break;
                    case 'Aku Mau Kopi ☕️': $this->coffeeGenerate($chatId); break;
                    case 'Tentang Developer 👨‍💻': $this->sendDeveloperInfo($chatId); break;
                    case 'Money Tracker 💸': $this->showMoneyTrackerMenu($chatId); break;
                    case 'Info Genshin 🎮': $this->showGenshinCategories($chatId); break;
                    case 'Poop Tracker 💩': $this->sendPoopTrackerInfo($chatId); break;
                    default:
                        if (strtolower($text) === 'halo') { $this->sendGreeting($chatId); }
                        else { $this->sendUnknownCommand($chatId); }
                        break;
                }
            }
        }
    }

    private function sendPoopTrackerInfo($chatId)
    {
        $message = "💩 *Poop Tracker*\n\n";
        $message .= "Fitur ini akan membantu Anda melacak kebiasaan buang air besar Anda. Anda dapat mencatat waktu, konsistensi, dan catatan tambahan.\n\n";
        $message .= "Untuk memulai, kirim pesan dengan format berikut:\n";
        $message .= "`/poop [konsistensi] [catatan]`\n";
        $message .= "Contoh: `/poop Normal Perasaan baik hari ini`\n\n";
        $message .= "Konsistensi yang umum digunakan: Cair, Lunak, Normal, Keras, Sangat Keras.\n\n";
        $message .= "Untuk melihat riwayat poop Anda, ketik `/poophistory`.\n\n";
        $message .= "Catatan: Fitur ini masih dalam pengembangan. Nantikan pembaruan selanjutnya!";

        $this->sendMessageSafely([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ]);
    }

    private function handlePoopCommand($chatId, $text){
        $pattern = '/^\/poop\s+(\w+)(?:\s+(.*))?$/i';
        $historyPattern = '/^\/poophistory$/i';

        if (preg_match($pattern, $text, $matches)) {
            $type = $matches[1];
            $notes = isset($matches[2]) ? trim($matches[2]) : null;

            PoopTracker::create([
                'user_id' => $chatId,
                'type' => $type,
                'notes' => $notes
            ]);

            $this->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => "✅ Catatan poop berhasil disimpan!\nTipe: *{$type}*\nCatatan: " . ($notes ? "*{$notes}*" : "_(tidak ada catatan)_"),
                'parse_mode' => 'Markdown'
            ]);
        } else if (preg_match($historyPattern, $text)) {
            $records = PoopTracker::where('user_id', $chatId)->latest()->take(10)->get();

            if ($records->isEmpty()) {
                $this->sendMessageSafely([
                    'chat_id' => $chatId,
                    'text' => "Anda belum memiliki catatan poop."
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

            $this->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);
        } else {
            $this->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => "Format salah. Gunakan `/poop [konsistensi] [catatan]` untuk mencatat poop atau `/poophistory` untuk melihat riwayat."
            ]);
        }
    }

    private function isUserAdmin($chatId)
    {
        $adminIdsString = env('TELEGRAM_ADMIN_ID');

        if (!$adminIdsString) {
            return false;
        }

        $adminIdsArray = explode(',', $adminIdsString);

        return in_array((string) $chatId, $adminIdsArray);    
    }

    /**
     * Menampilkan pilihan periode untuk laporan summary.
     */
    private function showSummaryOptions($chatId)
    {
        Log::info("Menampilkan pilihan summary untuk user: {$chatId}");
        $inlineKeyboard = [
            [
                Keyboard::inlineButton(['text' => 'Harian (Hari Ini)', 'callback_data' => 'summary_daily']),
            ],
            [
                Keyboard::inlineButton(['text' => 'Mingguan (Minggu Ini)', 'callback_data' => 'summary_weekly']),
            ],
            [
                Keyboard::inlineButton(['text' => 'Bulanan (Bulan Ini)', 'callback_data' => 'summary_monthly']),
            ]
        ];

        $this->sendMessageSafely([
            'chat_id' => $chatId,
            'text' => 'Silakan pilih periode laporan keuangan yang ingin Anda lihat:',
            'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard])
        ]);
    }

    /**
     * Menghitung dan menampilkan summary transaksi berdasarkan periode.
     */
    private function generateSummary($chatId, $messageId, $period)
    {
        $startDate = now()->startOfDay();
        $endDate = now()->endOfDay();
        $periodText = "Hari Ini";

        $dateFormat = 'H:i';

        switch ($period) {
            case 'weekly':
                $startDate = now()->startOfWeek();
                $endDate = now()->endOfWeek();
                $periodText = "Minggu Ini";
                $dateFormat = 'd M, H:i'; 
                break;
            case 'monthly':
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                $periodText = "Bulan Ini";
                $dateFormat = 'd M, H:i';
                break;
        }

        $incomes = \App\Models\Transaction::where('user_id', $chatId)->where('type', 'income')->whereBetween('created_at', [$startDate, $endDate])->latest()->get();
        $expenses = \App\Models\Transaction::where('user_id', $chatId)->where('type', 'expense')->whereBetween('created_at', [$startDate, $endDate])->latest()->get();

        $totalIncome = $incomes->sum('amount');
        $totalExpense = $expenses->sum('amount');
        $balance = $totalIncome - $totalExpense;
        $balanceSign = $balance >= 0 ? '+' : '-';
        $balanceColor = $balance >= 0 ? '🟢' : '🔴';

        $incomeDetails = "";
        if ($incomes->isNotEmpty()) {
            $incomeDetails = "\n*Rincian Pemasukan:*\n";
            foreach ($incomes as $income) {
                $date = $income->created_at->format($dateFormat);
                $incomeDetails .= "▫️ `{$date}` `Rp " . number_format($income->amount) . "` - " . $this->escapeMarkdown($income->description) . "\n";
            }
        }

        $expenseDetails = "";
        if ($expenses->isNotEmpty()) {
            $expenseDetails = "\n*Rincian Pengeluaran:*\n";
            foreach ($expenses as $expense) {
        $date = $expense->created_at->format($dateFormat);
    
        $expenseDetails .= "▫️ {$date} | Rp " . number_format($expense->amount) . " - " . $expense->description . "\n";
            }
        }
        
        $message = "📊 *Laporan Keuangan - {$periodText}*\n";
        $message .= "🗓️ Periode: " . $startDate->format('d M Y') . " - " . $endDate->format('d M Y') . "\n";
        $message .= "──────────────────────────────\n";
        $message .= "✅ *Total Pemasukan:*\n`Rp " . number_format($totalIncome) . "`\n";
        $message .= $incomeDetails;
        $message .= "\n❌ *Total Pengeluaran:*\n`Rp " . number_format($totalExpense) . "`\n";
        $message .= $expenseDetails;
        $message .= "\n──────────────────────────────\n";
        $message .= "{$balanceColor} *Sisa Saldo:*\n`{$balanceSign} Rp " . number_format(abs($balance)) . "`";

        try {
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal edit pesan summary: ' . $e->getMessage());
        }
    }

    /** Menampilkan menu Money Tracker dengan instruksi.
     */
    private function showMoneyTrackerMenu($chatId)
    {
        $message = "Selamat datang di Money Tracker! 💸\n\n";
        $message .= "Gunakan format berikut untuk mencatat transaksi:\n\n";
        $message .= "Pemasukan:\n`+ [jumlah] [deskripsi]`\nContoh: `+ 50000 Gaji`\n\n";
        $message .= "Pengeluaran:\n`- [jumlah] [deskripsi]`\nContoh: `- 15000 Makan siang`\n\n";
        $message .= "Untuk melihat laporan, ketik `/summary`";

        $this->sendMessageSafely([
            'chat_id' => $chatId,
            'text'=> "Selamat datang di Money Tracker! 💸\n\nGunakan format berikut untuk mencatat transaksi:\n\nPemasukan:\n+ [jumlah] [deskripsi]\nContoh: + 500000 Gaji\n\nPengeluaran:\n- [jumlah] [deskripsi]\nContoh: - 15000 Makan siang\n\nUntuk melihat laporan, ketik /summary atau /laporan\nUntuk menghapus laporan, ketik /hapus\nUntuk mengedit laporan, ketik /edit",
        ]);
    }

    /**
     * Menampilkan 5 transaksi terakhir pengguna dengan tombol hapus inline.
     */
    private function showRecentTransactionsForDeletion($chatId)
    {
        Log::info("Menampilkan transaksi untuk dihapus bagi user: {$chatId}");
        $transactions = \App\Models\Transaction::where('user_id', $chatId)
            ->latest()
            ->get();

        if ($transactions->isEmpty()) {
            $this->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => 'Anda belum memiliki transaksi untuk dihapus.'
            ]);
            return;
        }

        $message = "Klik tombol di bawah untuk menghapus transaksi:\n\n";
        $inlineKeyboard = [];

        foreach ($transactions as $transaction) {
            $type = $transaction->type === 'income' ? '+' : '-';
            $amount = number_format($transaction->amount);
            $date = $transaction->created_at->format('d M');

            $message .= "🆔 *{$transaction->id}* | {$date} | {$type}\n";
            $message .= "`Rp {$amount}` - _{$transaction->description}_\n\n";

            $inlineKeyboard[] = [
                Keyboard::inlineButton([
                    'text' => "❌ Hapus Transaksi ID: {$transaction->id}",
                    'callback_data' => 'delete_trx_' . $transaction->id 
                ])
            ];
        }

        $this->sendMessageSafely([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard])
        ]);
    }

    private function recordTransaction($chatId, $text)
    {
        $pattern = '/^([+\-])\s*(\d+)\s*(.*)$/';

        if (preg_match($pattern, $text, $matches)) {
            $symbol = $matches[1];
            $amount = (int) $matches[2];
            $description = trim($matches[3]);

            $type = ($symbol === '+') ? 'income' : 'expense';

            if (empty($description)) {
                $this->sendMessageSafely([
                    'chat_id' => $chatId,
                    'text' => '⚠️ Deskripsi tidak boleh kosong. Contoh: `+ 50000 Gaji`',
                    'parse_mode' => 'Markdown'
                ]);
                return;
            }

            Transaction::create([
                'user_id' => $chatId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description
            ]);

            $this->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => "✅ Transaksi berhasil dicatat:\n*{$type}* - Rp " . number_format($amount) . " - {$description}",
                'parse_mode' => 'Markdown'
            ]);

        } else {
            $this->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => 'Format salah. Gunakan `+` untuk pemasukan atau `-` untuk pengeluaran.' . "\nContoh: `- 15000 Kopi`",
                'parse_mode' => 'Markdown'
            ]);
        }
    }

    /**
     * Membersihkan teks dari karakter khusus MarkdownV2 agar aman dikirim.
     *
     * @param string $text Teks yang akan dibersihkan.
     * @return string Teks yang sudah aman.
     */
    private function escapeMarkdown($text)
    {
        $escapeChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        
        return str_replace($escapeChars, array_map(function($char) {
            return '\\'.$char;
        }, $escapeChars), $text);
    }

    private function handleAdminCommands($chatId, $text)
    {
        if (!$this->isUserAdmin($chatId)) {
            $this->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => '🚫 Anda tidak memiliki izin untuk menggunakan perintah ini.'
            ]);
            return;
        }

        if ($text === '/listusers') {
            $users = TelegramUser::latest('last_interaction_at')->take(10)->get();
            
            if ($users->isEmpty()) {
                $this->sendMessageSafely(['chat_id' => $chatId, 'text' => 'Belum ada pengguna yang tercatat.']);
                return;
            }

            $message = "👥 *10 Pengguna Terakhir:*\n\n";
            foreach ($users as $user) {
                $username = $user->username ? "@" . $user->username : "N/A";
                $message .= "ID: `{$user->id}`\n";
                $message .= "Nama: {$user->first_name}\n";
                $message .= "Username: {$username}\n";
                $message .= "User ID: {$user->user_id}\n";
                $message .= "Last Active: {$user->last_interaction_at}\n";
                $message .= "--------------------\n";
            }
            $this->sendMessageSafely(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'Markdown']);
        }
        else if (str_starts_with($text, '/usercommands ')) {
            $targetUserId = substr($text, 14); 

            if (!is_numeric($targetUserId)) {
                $this->sendMessageSafely(['chat_id' => $chatId, 'text' => 'Format salah. Gunakan: `/usercommands [user_id]`']);
                return;
            }

            $commands = TelegramUserCommand::where('user_id', $targetUserId)
                ->latest('created_at')->take(10)->get();

            if ($commands->isEmpty()) {
                $this->sendMessageSafely(['chat_id' => $chatId, 'text' => "Tidak ditemukan perintah untuk User ID: `{$targetUserId}`"]);
                return;
            }

            $message = "📜 *10 Perintah Terakhir dari User ID `{$targetUserId}`:*\n\n";
            foreach ($commands as $command) {
                $rawCommandText = $command->command;

                $safeCommandText = $this->escapeMarkdown($rawCommandText);

                $message .= "`" . $command->created_at->format('Y-m-d H:i') . "`\n";
                $message .= "> {$safeCommandText}\n\n"; 
            }
            
        $this->sendMessageSafely(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'Markdown']);
        }
        else {
            $this->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => "Perintah admin tidak dikenal. Gunakan:\n`/listusers`\n`/usercommands [user_id]`"
            ]);
        }
    }

    /**
     * Fungsi untuk masuk ke mode chat Gemini.
     */
    private function enterGeminiChatMode(\App\Models\TelegramUser $user, $chatId)
    {
        $user->state = 'gemini_chat';
        $user->save();

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "🤖 Anda sekarang dalam mode chat dengan AI Gemini.\n\nSilakan ajukan pertanyaan apa pun.\nKetik `/selesai` untuk keluar dari mode ini (Jika tidak ada response dalam 5 menit, maka akan otomatis keluar dari mode AI)."
        ]);
    }

    /**
     * Fungsi untuk keluar dari mode chat Gemini.
     */
    private function exitGeminiChatMode(\App\Models\TelegramUser $user, $chatId)
    {
        $user->state = 'normal';
        $user->save();

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "✅ Keluar/selesai berhasil. Kembali ke menu utama."
        ]);

        $this->showMainMenu($chatId);
    }

    /**
     * Mengirim pertanyaan ke Gemini.
     */
    private function askGemini($chatId, $update)
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            Log::error('GEMINI_API_KEY is not set in .env');
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, layanan AI sedang tidak terkonfigurasi.']);
            return;
        }

        Telegram::sendChatAction(['chat_id' => $chatId, 'action' => 'typing']);

        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;

        try {
            $response = Http::timeout(60)->post($apiUrl, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $update]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                if (!empty($response->json()['candidates'])) {
                    $reply = $response->json()['candidates'][0]['content']['parts'][0]['text'];
                    Telegram::sendMessage([
                        'chat_id' => $chatId, 
                        'text' => $reply,
                        'parse_mode' => 'Markdown'
                    ]);
                } else {
                    Log::warning('Gemini API call successful but no candidates returned. Prompt may be blocked.');
                    Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, pertanyaan Anda tidak dapat diproses saat ini. Coba dengan pertanyaan lain.']);
                }
            } else {
                Log::error('Gemini API Error: ' . $response->body());
                if ($response->status() == 429) {
                    Telegram::sendMessage(['chat_id' => $chatId, 'text' => '🚧 Maaf, layanan AI Chat sedang sibuk karena telah mencapai limit. Silakan coba lagi nanti. 🙏']);
                } else {
                    Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, terjadi kesalahan saat menghubungi layanan AI.']);
                }
            }
        } catch (\Exception $e) {
            Log::error('Exception during Gemini API call: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Halo, ada yang bisa saya bantu?']);
        }
    }

    /**
     * Mencatat/memperbarui data pengguna dan MENGEMBALIKAN model User.
     * @param \Telegram\Bot\Objects\Update $update
     * @return \App\Models\TelegramUser|null
     */
    private function logUserActivity($update)
    {
        try {
            $from = null;
            if ($update->isType('callback_query')) {
                $from = $update->getCallbackQuery()->getFrom();
            } else if ($update->getMessage()) {
                $from = $update->getMessage()->getFrom();
            }

            if (!$from) {
                Log::warning('Tidak bisa mendapatkan data user dari update Telegram.');
                return null;
            }

            // =================================================================
            // ==> INI BAGIAN TERPENTING: Gunakan 'return' <==
            // updateOrCreate akan mencari user, jika tidak ada maka akan dibuat.
            // Fungsi ini juga langsung mengembalikan model User yang bisa kita gunakan.
            // =================================================================
            return \App\Models\TelegramUser::updateOrCreate(
                ['user_id' => $from->getId()], 
                [
                    'username'   => $from->getUsername(),
                    'first_name' => $from->getFirstName(),
                    'last_name'  => $from->getLastName(),
                    'last_interaction_at' => now(),

                ]
            );

        } catch (\Exception $e) {
            Log::error('Gagal mencatat pengguna Telegram: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }


    /**
     * Menampilkan menu utama dengan keyboard.
     */
    private function showMainMenu($chatId)
    {
        $keyboard = [
            ['Cuaca di Jakarta 🌤️', 'Nasihat Bijak 💡'],
            ['Fakta Kucing 🐱', 'Money Tracker 💸'],
            ['Aku Mau Kopi ☕️','Info Genshin 🎮'],
            ['AI Chat 🤖','Poop Tracker 💩'],
            ['Tentang Developer 👨‍💻'],
        ];

        $reply_markup = Keyboard::make([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Hai! 👋 Silakan pilih salah satu menu di bawah ini:',
            'reply_markup' => $reply_markup
        ]);
    }

    /**
     * Mengambil kategori dari API dan menampilkannya sebagai tombol inline.
     */
    private function showGenshinCategories($chatId, $messageId = null)
    {
        try {
            $response = Http::get('https://genshin.jmp.blue/');
            if ($response->successful()) {
                $categories = $response->json()['types'];
                $inlineKeyboard = [];

                foreach ($categories as $category) {
                    $inlineKeyboard[] = [
                        Keyboard::inlineButton([
                            'text' => ucwords(str_replace('-', ' ', $category)),
                            'callback_data' => 'category_' . $category
                        ])
                    ];
                }

                $messageData = [
                    'chat_id' => $chatId,
                    'text' => 'Pilih kategori Genshin Impact yang ingin Anda lihat:',
                    'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard])
                ];

                if ($messageId) {
                    $messageData['message_id'] = $messageId;
                    Telegram::editMessageText($messageData);
                } else {
                    Telegram::sendMessage($messageData);
                }
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, gagal mengambil data kategori dari API.']);
            }
        } catch (\Exception $e) {
            Log::error('Error ambil kategori Genshin: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat menghubungi API Genshin.']);
        }
    }

    /**
     * Menampilkan daftar item dalam sebuah kategori dengan pagination.
     */
    private function showItemsInCategory($chatId, $messageId, $category, $page = 1)
    {
        define('ITEMS_PER_PAGE', 20);

        try {
            $response = Http::get('https://genshin.jmp.blue/' . $category);

            if ($response->successful()) {
                $allItems = $response->json();
                $totalItems = count($allItems);
                $totalPages = ceil($totalItems / ITEMS_PER_PAGE);
                $offset = ($page - 1) * ITEMS_PER_PAGE;
                $itemsForCurrentPage = array_slice($allItems, $offset, ITEMS_PER_PAGE);
                $inlineKeyboard = [];
                $row = [];

                foreach ($itemsForCurrentPage as $item) {
                    $button = Keyboard::inlineButton([
                        'text' => ucwords(str_replace('-', ' ', $item)),
                        'callback_data' => 'item_' . $category . '_' . $item
                    ]);
                    $row[] = $button;
                    if (count($row) == 2) {
                        $inlineKeyboard[] = $row;
                        $row = [];
                    }
                }
                if (!empty($row)) {
                    $inlineKeyboard[] = $row;
                }

                $navKeyboard = [];
                if ($page > 1) { 
                    $navKeyboard[] = Keyboard::inlineButton([
                        'text' => '⬅️ Prev', 
                        'callback_data' => 'genshin_page_' . $category . '_' . ($page - 1)
                    ]);
                }

                $navKeyboard[] = Keyboard::inlineButton(['text' => "Page {$page}/{$totalPages}", 'callback_data' => 'no_action']);

                if ($page < $totalPages) {
                    $navKeyboard[] = Keyboard::inlineButton([
                        'text' => 'Next ➡️', 
                        'callback_data' => 'genshin_page_' . $category . '_' . ($page + 1)
                    ]);
                }
                
                if (!empty($navKeyboard)) {
                    $inlineKeyboard[] = $navKeyboard;
                }

                $inlineKeyboard[] = [Keyboard::inlineButton(['text' => '⬅️ Kembali ke Kategori', 'callback_data' => 'back_to_categories'])];

                Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => 'Silakan pilih item dari kategori *' . ucwords($category) . '* (Halaman ' . $page . '):',
                    'parse_mode' => 'Markdown',
                    'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard])
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error ambil item Genshin ($category): " . $e->getMessage());
        }
    }

    /**
     * Menampilkan detail dari item yang dipilih.
     */
    private function showItemDetails($chatId, $messageId, $category, $itemName)
    {
        try {
            $response = Http::get("https://genshin.jmp.blue/{$category}/{$itemName}");

            if ($response->successful()) {
                $details = $response->json();
                
                if (empty($details)) {
                    Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, detail untuk item ini tidak ditemukan.']);
                    return;
                }

                $text = "✨ *" . ($details['name'] ?? 'Detail Item') . "* ✨\n\n";

                $ignoreKeys = ['name', 'id', 'images', 'slug'];

                foreach ($details as $key => $value) {
                    if (in_array($key, $ignoreKeys)) {
                        continue;
                    }
                    if (is_scalar($value) && !empty($value)) {
                        $formattedKey = ucwords(str_replace(['-', '_'], ' ', $key));
                        $text .= "🔹 *" . $formattedKey . ":* " . $value . "\n";
                    }
                }

                $inlineKeyboard = [[
                    Keyboard::inlineButton([
                        'text' => '⬅️ Kembali ke ' . ucwords($category),
                        'callback_data' => 'category_' . $category
                    ])
                ]];

                Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => rtrim($text),
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true, 
                    'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard])
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error ambil detail Genshin ({$itemName}): " . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat mengambil detail item.']);
        }
    }

    /**
     * Menghasilkan dan mengirim gambar kopi acak.
     */
    private function coffeeGenerate($chatId)
    {
        try {
            $response = Http::get('https://coffee.alexflipnote.dev/random.json');
            if ($response->successful()) {
                $imageUrl = $response->json()['file'];
                $image = InputFile::create($imageUrl, 'coffee.jpg');
                Telegram::sendPhoto([
                    'chat_id' => $chatId,
                    'photo' => $image,
                    'caption' => '☕️ Nikmati secangkir kopi virtual!'
                ]);
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, tidak bisa menghasilkan gambar kopi sekarang.']);
            }
        } catch (\Exception $e) {
            Log::error('Error generate kopi: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat menghasilkan gambar kopi.']);
        }
    }

    /**
     * Mengirim informasi cuaca.
     */
    private function sendWeatherInfo($chatId)
    {
        try {
            $response = Http::get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => -6.2, 
                'longitude' => 106.8,
                'current_weather' => true,
            ]);

            if ($response->successful()) {
                $weather = $response->json()['current_weather'];
                $time = date('d M Y, H:i', strtotime($weather['time']));
                
                $msg = "🌤 Cuaca saat ini di Jakarta:\n\n";
                $msg .= "Suhu: {$weather['temperature']}°C\n";
                $msg .= "Kecepatan Angin: {$weather['windspeed']} km/j\n";
                $msg .= "Update Terakhir: {$time} WIB";

                Telegram::sendMessage(['chat_id' => $chatId, 'text' => $msg]);
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, tidak bisa mengambil data cuaca sekarang.']);
            }
        } catch (\Exception $e) {
            Log::error('Error ambil weather: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat mengambil data cuaca.']);
        }
    }

    /**
     * Mengirim nasihat acak.
     */
    private function sendAdvice($chatId)
    {
        try {
            $response = Http::get('https://api.adviceslip.com/advice');
            if ($response->successful()) {
                $advice = $response->json()['slip']['advice'];
                $translated = GoogleTranslate::trans($advice, 'id', 'en');
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => "💡 Nasihat hari ini:\n\"$translated\""]);
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, tidak bisa mengambil nasihat sekarang.']);
            }
        } catch (\Exception $e) {
            Log::error('Error ambil nasihat: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat mengambil nasihat.']);
        }
    }

    /**
     * Mengirim fakta kucing acak.
     */
    private function sendCatFact($chatId)
    {
        try {
            $response = Http::get('https://catfact.ninja/fact');
            if ($response->successful()) {
                $fact = $response->json()['fact'];
                $translated = GoogleTranslate::trans($fact, 'id', 'en');
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => "🐱 Fakta tentang kucing:\n\"$translated\""]);
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, saya tidak bisa mengambil fakta kucing saat ini.']);
            }
        } catch (\Exception $e) {
            Log::error('Error ambil fakta kucing: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat mengambil fakta kucing.']);
        }
    }

    /**
     * Mengirim info tentang developer.
     */
    private function sendDeveloperInfo($chatId)
    {
        $responses = [
            "Teguh Waluyojati adalah seorang yang berprofesi sebagai professional Full-Stack Developer.\nhttps://teguhwaluyojati.github.io/"
        ];
        $randomResponse = $responses[array_rand($responses)];
        Telegram::sendMessage(['chat_id' => $chatId, 'text' => $randomResponse]);
    }
    
    /**
     * Mengirim sapaan halo.
     */
    private function sendGreeting($chatId)
    {
        $responses = [
            'Halo juga! Ada yang bisa dibantu? Tampilkan menu dengan /menu',
            'Hai! Semoga harimu menyenangkan. Coba ketik /menu',
            'Halo! Senang bertemu denganmu. Lihat menu dengan /menu',
        ];
        $randomResponse = $responses[array_rand($responses)];
        Telegram::sendMessage(['chat_id' => $chatId, 'text' => $randomResponse]);
    }

    /**
     * Mengirim pesan jika perintah tidak dikenali.
     */
    private function sendUnknownCommand($chatId)
    {
        $text = 'Maaf, saya tidak mengerti perintah itu. Silakan gunakan tombol menu di bawah atau ketik /menu untuk memulai.';
        $this->showMainMenu($chatId);
    }

    /**
     * Helper untuk mengirim pesan dengan aman dan menangani error umum.
     * Terutama error "chat not found" saat pengguna memblokir bot.
     *
     * @param array $params Parameter untuk fungsi sendMessage
     * @return void
     */
    private function sendMessageSafely(array $params)
    {
        try {
            Telegram::sendMessage($params);
        } catch (\Telegram\Bot\Exceptions\TelegramOtherException $e) {
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

    /**
     * API untuk broadcast daily pengeluaran ke semua user.
     */
    public function broadcastDailyExpenses()
    {
        Log::info("Memulai proses broadcast pengeluaran harian...");
        $yesterday = now()->subDay();

        $targetUserIds = \App\Models\Transaction::distinct()
            ->pluck('user_id')
            ->all();

        if (empty($targetUserIds)) {
            Log::info('Tidak ada pengguna yang pernah bertransaksi. Proses selesai.');
            return;
        }

        Log::info("Menyiapkan broadcast untuk " . count($targetUserIds) . " pengguna...");

        foreach ($targetUserIds as $userId) {
            
            $userExpenses = \App\Models\Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->whereDate('created_at', $yesterday)
                ->get();

            $yesterdayDate = $yesterday->format('d M Y');
            $message = "📊 *Laporan Pengeluaran Harian Anda*\n\n";
            $message .= "Berikut adalah rangkuman pengeluaranmu untuk kemarin ({$yesterdayDate}):\n\n";

            if ($userExpenses->isEmpty()) {
                $message .= "Anda tidak memiliki pengeluaran kemarin. Luar biasa!";
            } else {
                $totalExpense = 0;
                foreach ($userExpenses as $expense) {
                    $time = $expense->created_at->format('H:i');
                    $amount = number_format($expense->amount);
                    $description = $this->escapeMarkdown($expense->description ?? 'Tidak ada deskripsi');

                    $message .= "▫️ `{$time}` | `Rp {$amount}` - {$description}\n";
                    $totalExpense += $expense->amount;
                }
                $message .= "\n──────────────────────────────\n";
                $message .= "❌ *Total Pengeluaran:* `Rp " . number_format($totalExpense) . "`";
            }

            try {
                Telegram::sendMessage([
                    'chat_id' => $userId,
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ]);
                Log::info("Berhasil broadcast ke user_id: {$userId}");
            } catch (\Exception $e) {
                Log::error("Gagal kirim broadcast ke user_id: {$userId}. Error: " . $e->getMessage());
            }
        }

        Log::info("Proses broadcast harian selesai.");
    }
}