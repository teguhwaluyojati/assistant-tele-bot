<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TelegramUser;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class SendDailyExpenseSummary extends Command
{
    protected $signature = 'app:send-daily-expense-summary';
    protected $description = 'Mengirim rangkuman pengeluaran harian ke setiap pengguna aktif.';

    public function handle()
    {
        $this->info('Memulai proses broadcast rangkuman harian...');

        $yesterday = now()->subDay();

        $expenses = Transaction::where('type', 'expense')
            ->whereDate('created_at', $yesterday)
            ->get()
            ->groupBy('user_id');

        if ($expenses->isEmpty()) {
            $this->info('Tidak ada pengeluaran kemarin. Proses broadcast selesai.');
            return;
        }

        foreach ($expenses as $userId => $userExpenses) {
            $totalExpense = $userExpenses->sum('amount');
            $transactionCount = $userExpenses->count();
            
            $message = "☀️ *Laporan Pengeluaran Harian*\n\n";
            $message .= "Halo! Berikut adalah rangkuman pengeluaranmu untuk kemarin (" . $yesterday->format('d M Y') . "):\n\n";
            $message .= "Total Pengeluaran: `Rp " . number_format($totalExpense) . "`\n";
            $message .= "Jumlah Transaksi: `{$transactionCount}`\n\n";
            $message .= "Semoga harimu menyenangkan!";

            try {
                Telegram::sendMessage([
                    'chat_id' => $userId,
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ]);
                $this->info("Pesan terkirim ke user: {$userId}");
            } catch (\Exception $e) {
                $this->error("Gagal mengirim pesan ke user: {$userId}. Error: " . $e->getMessage());
                Log::warning("Broadcast gagal untuk user {$userId}, kemungkinan bot diblokir.");
            }
        }

        $this->info('Proses broadcast rangkuman harian berhasil diselesaikan.');
    }
}