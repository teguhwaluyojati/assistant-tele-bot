<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Services\Telegram\TelegramMessageService;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class FinanceController extends Controller
{
    private TelegramMessageService $messageService;

    public function __construct(TelegramMessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    public function startEditMode(TelegramUser $user): void
    {
        $transactions = Transaction::where('user_id', $user->user_id)
            ->latest()
            ->limit(10)
            ->get();

        if ($transactions->isEmpty()) {
            $this->messageService->sendMessageSafely([
                'chat_id' => $user->user_id,
                'text' => 'Anda belum memiliki transaksi untuk diedit.',
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
            $message .= '_' . $this->messageService->escapeMarkdown($transaction->description) . "_\n\n";

            $inlineKeyboard[] = [
                Keyboard::inlineButton([
                    'text' => "Pilih Transaksi ID: {$transaction->id}",
                    'callback_data' => 'select_edit_trx_' . $transaction->id,
                ]),
            ];
        }

        $inlineKeyboard[] = [Keyboard::inlineButton(['text' => 'Batalkan', 'callback_data' => 'cancel_generic'])];

        $this->messageService->sendMessageSafely([
            'chat_id' => $user->user_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard]),
        ]);
    }

    public function showEditOptions(TelegramUser $user, $messageId, $transactionId): void
    {
        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            $this->messageService->sendMessageSafely([
                'chat_id' => $user->user_id,
                'text' => 'Error: Transaksi tidak ditemukan lagi.',
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
            ],
        ];

        $type = $transaction->type === 'income' ? 'Pemasukan' : 'Pengeluaran';
        $amount = number_format($transaction->amount);
        $date = $transaction->created_at->format('d M Y H:i');

        $message = "Anda akan mengedit Transaksi ID: *{$transactionId}*\n\n";
        $message .= "*Tipe:* {$type}\n";
        $message .= "*Jumlah:* Rp {$amount}\n";
        $message .= "*Tanggal:* {$date}\n";
        $message .= "*Deskripsi:* {$transaction->description}\n\n";
        $message .= 'Apa yang ingin Anda ubah?';

        try {
            Telegram::editMessageText([
                'chat_id' => $user->user_id,
                'message_id' => $messageId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard]),
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal menampilkan opsi edit: ' . $e->getMessage());
            $this->messageService->sendMessageSafely([
                'chat_id' => $user->user_id,
                'text' => 'Silakan pilih field yang ingin diedit:',
            ]);
        }
    }

    public function handleEditingChatMode(TelegramUser $user, $update): void
    {
        if (!$update->isType('callback_query')) {
            return;
        }

        $callbackQuery = $update->getCallbackQuery();
        $data = $callbackQuery->getData();

        \App\Models\TelegramUserCommand::create([
            'user_id' => $user->user_id,
            'command' => 'CALLBACK: ' . $data,
        ]);

        if (str_starts_with($data, 'edit_field_')) {
            [, , $field, $transactionId] = explode('_', $data);

            $this->promptForNewValue($user, $field, $transactionId);
        } elseif ($data === 'cancel_edit_full') {
            $user->state = 'normal';
            $user->save();
            Telegram::editMessageText([
                'chat_id' => $user->user_id,
                'message_id' => $callbackQuery->getMessage()->getMessageId(),
                'text' => 'Proses edit dibatalkan.',
                'reply_markup' => null,
            ]);
        }
    }

    public function promptForNewValue(TelegramUser $user, $field, $transactionId): void
    {
        $user->state = "editing_{$field}_{$transactionId}";
        $user->save();

        $promptMessage = '';
        switch ($field) {
            case 'amount':
                $promptMessage = 'Silakan masukkan *jumlah* baru untuk transaksi ini (hanya angka).';
                break;
            case 'description':
                $promptMessage = 'Silakan masukkan *deskripsi* baru untuk transaksi ini.';
                break;
            case 'date':
                $promptMessage = 'Silakan masukkan *tanggal* baru dengan format YYYY-MM-DD (Contoh: `2025-09-10`).';
                break;
        }

        $promptMessage .= "\n\nKetik `/batal` untuk membatalkan proses edit ini.";

        $this->messageService->sendMessageSafely([
            'chat_id' => $user->user_id,
            'text' => $promptMessage,
            'parse_mode' => 'Markdown',
        ]);
    }

    public function handleEditTransactionInput(TelegramUser $user, $newValue): void
    {
        $chatId = $user->user_id;

        if (strtolower($newValue) === '/batal') {
            $user->state = 'normal';
            $user->save();
            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => '✅ Proses edit dibatalkan.',
            ]);
            return;
        }

        [, $field, $transactionId] = explode('_', $user->state);

        $transaction = Transaction::where('id', $transactionId)
            ->where('user_id', $chatId)
            ->first();

        if (!$transaction) {
            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => '⚠️ Gagal memperbarui, transaksi tidak ditemukan.',
            ]);
            $user->state = 'normal';
            $user->save();
            return;
        }

        if ($field === 'amount') {
            $cleanValue = preg_replace('/[^0-9]/', '', $newValue);

            if (!is_numeric($cleanValue)) {
                $this->messageService->sendMessageSafely([
                    'chat_id' => $chatId,
                    'text' => '❌ Jumlah harus berupa angka. Silakan coba lagi.',
                ]);
                return;
            }
            $newValue = $cleanValue;
        }

        $transaction->{$field} = $newValue;
        $transaction->save();

        $user->state = 'normal';
        $user->save();

        $displayValue = ($field === 'amount') ? number_format($newValue, 0, ',', '.') : $newValue;

        $this->messageService->sendMessageSafely([
            'chat_id' => $chatId,
            'text' => "✅ Transaksi ID {$transactionId} berhasil diperbarui!\nData baru: {$displayValue}",
        ]);
    }

    public function showSummaryOptions($chatId): void
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
            ],
            [
                Keyboard::inlineButton(['text' => 'Pilih Hari', 'callback_data' => 'summary_custom']),
            ],
        ];

        $this->messageService->sendMessageSafely([
            'chat_id' => $chatId,
            'text' => 'Silakan pilih periode laporan keuangan yang ingin Anda lihat:',
            'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard]),
        ]);
    }

    public function generateSummary($chatId, $messageId, $period): void
    {
        $startDate = now()->startOfDay();
        $endDate = now()->endOfDay();
        $periodText = 'Hari Ini';

        $dateFormat = 'H:i';

        switch ($period) {
            case 'weekly':
                $startDate = now()->startOfWeek();
                $endDate = now()->endOfWeek();
                $periodText = 'Minggu Ini';
                $dateFormat = 'd M, H:i';
                break;
            case 'monthly':
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                $periodText = 'Bulan Ini';
                $dateFormat = 'd M, H:i';
                break;
        }

        $incomes = Transaction::where('user_id', $chatId)
            ->where('type', 'income')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->get();

        $expenses = Transaction::where('user_id', $chatId)
            ->where('type', 'expense')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->get();

        $totalIncome = $incomes->sum('amount');
        $totalExpense = $expenses->sum('amount');
        $balance = $totalIncome - $totalExpense;
        $balanceSign = $balance >= 0 ? '+' : '-';
        $balanceColor = $balance >= 0 ? '🟢' : '🔴';

        $incomeDetails = '';
        if ($incomes->isNotEmpty()) {
            $incomeDetails = "\n*Rincian Pemasukan:*\n";
            foreach ($incomes as $income) {
                $date = $income->created_at->format($dateFormat);
                $incomeDetails .= "▫️ `{$date}` `Rp " . number_format($income->amount) . "` - " . $this->messageService->escapeMarkdown($income->description) . "\n";
            }
        }

        $expenseDetails = '';
        if ($expenses->isNotEmpty()) {
            $expenseDetails = "\n*Rincian Pengeluaran:*\n";
            foreach ($expenses as $expense) {
                $date = $expense->created_at->format($dateFormat);
                $expenseDetails .= "▫️ {$date} | Rp " . number_format($expense->amount) . ' - ' . $expense->description . "\n";
            }
        }

        $message = "📊 *Laporan Keuangan - {$periodText}*\n";
        $message .= '🗓️ Periode: ' . $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y') . "\n";
        $message .= "──────────────────────────────\n";
        $message .= "✅ *Total Pemasukan:*\n`Rp " . number_format($totalIncome) . "`\n";
        $message .= $incomeDetails;
        $message .= "\n❌ *Total Pengeluaran:*\n`Rp " . number_format($totalExpense) . "`\n";
        $message .= $expenseDetails;
        $message .= "\n──────────────────────────────\n";
        $message .= "{$balanceColor} *Sisa Saldo:*\n`{$balanceSign} Rp " . number_format(abs($balance)) . '`';

        try {
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal edit pesan summary: ' . $e->getMessage());
        }
    }

    public function showMoneyTrackerMenu($chatId): void
    {
        $this->messageService->sendMessageSafely([
            'chat_id' => $chatId,
            'text' => "Selamat datang di Money Tracker! 💸\n\nGunakan format berikut untuk mencatat transaksi:\n\nPemasukan:\n+ [jumlah] [deskripsi]\nContoh: + 500000 Gaji\n\nPengeluaran:\n- [jumlah] [deskripsi]\nContoh: - 15000 Makan siang\n\nUntuk melihat laporan, ketik /summary atau /laporan\nUntuk menghapus laporan, ketik /hapus\nUntuk mengedit laporan, ketik /edit",
        ]);
    }

    public function showRecentTransactionsForDeletion($chatId): void
    {
        Log::info("Menampilkan transaksi untuk dihapus bagi user: {$chatId}");
        $transactions = Transaction::where('user_id', $chatId)
            ->latest()
            ->get();

        if ($transactions->isEmpty()) {
            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => 'Anda belum memiliki transaksi untuk dihapus.',
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
                    'callback_data' => 'delete_trx_' . $transaction->id,
                ]),
            ];
        }

        $this->messageService->sendMessageSafely([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard]),
        ]);
    }

    public function recordTransaction($chatId, $text): void
    {
        $pattern = '/^([+\-])\s*(\d+)\s*(.*)$/';

        if (preg_match($pattern, $text, $matches)) {
            $symbol = $matches[1];
            $amount = (int) $matches[2];
            $description = trim($matches[3]);

            $type = ($symbol === '+') ? 'income' : 'expense';

            if (empty($description)) {
                $this->messageService->sendMessageSafely([
                    'chat_id' => $chatId,
                    'text' => '⚠️ Deskripsi tidak boleh kosong. Contoh: `+ 50000 Gaji`',
                    'parse_mode' => 'Markdown',
                ]);
                return;
            }

            Transaction::create([
                'user_id' => $chatId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
            ]);

            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => "✅ Transaksi berhasil dicatat:\n*{$type}* - Rp " . number_format($amount) . " - {$description}",
                'parse_mode' => 'Markdown',
            ]);
        } else {
            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => 'Format salah. Gunakan `+` untuk pemasukan atau `-` untuk pengeluaran.' . "\nContoh: `- 15000 Kopi`",
                'parse_mode' => 'Markdown',
            ]);
        }
    }

    public function deleteTransactionFromCallback($chatId, $messageId, $transactionId): void
    {
        $transaction = Transaction::where('user_id', $chatId)
            ->where('id', $transactionId)
            ->first();

        if ($transaction) {
            $deletedDescription = $transaction->description;
            $transaction->delete();
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "✅ Transaksi '{$deletedDescription}' (ID: {$transactionId}) berhasil dihapus",
                'reply_markup' => null,
            ]);
        } else {
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => '⚠️ Transaksi tidak dapat ditemukan atau sudah dihapus.',
                'reply_markup' => null,
            ]);
        }
    }

    public function broadcastDailyExpenses(): void
    {
        Log::info('Memulai proses broadcast pengeluaran harian...');
        $yesterday = now()->subDay();

        $targetUserIds = Transaction::distinct()
            ->pluck('user_id')
            ->all();

        if (empty($targetUserIds)) {
            Log::info('Tidak ada pengguna yang pernah bertransaksi. Proses selesai.');
            return;
        }

        Log::info('Menyiapkan broadcast untuk ' . count($targetUserIds) . ' pengguna...');

        foreach ($targetUserIds as $userId) {
            $userExpenses = Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->whereDate('created_at', $yesterday)
                ->get();

            $yesterdayDate = $yesterday->format('d M Y');
            $message = "📊 *Laporan Pengeluaran Harian Anda*\n\n";
            $message .= "Berikut adalah rangkuman pengeluaranmu untuk kemarin ({$yesterdayDate}):\n\n";

            if ($userExpenses->isEmpty()) {
                $message .= 'Anda tidak memiliki pengeluaran kemarin. Luar biasa!';
            } else {
                $totalExpense = 0;
                foreach ($userExpenses as $expense) {
                    $time = $expense->created_at->format('H:i');
                    $amount = number_format($expense->amount);
                    $description = $this->messageService->escapeMarkdown($expense->description ?? 'Tidak ada deskripsi');

                    $message .= "▫️ `{$time}` | `Rp {$amount}` - {$description}\n";
                    $totalExpense += $expense->amount;
                }
                $message .= "\n──────────────────────────────\n";
                $message .= "❌ *Total Pengeluaran:* `Rp " . number_format($totalExpense) . '`';
            }

            try {
                Telegram::sendMessage([
                    'chat_id' => $userId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                ]);
                Log::info("Berhasil broadcast ke user_id: {$userId}");
            } catch (\Exception $e) {
                Log::error("Gagal kirim broadcast ke user_id: {$userId}. Error: " . $e->getMessage());
            }
        }

        Log::info('Proses broadcast harian selesai.');
    }
}
