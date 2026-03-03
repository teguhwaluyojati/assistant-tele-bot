<?php

namespace App\Console\Commands;

use App\Models\TelegramUser;
use Illuminate\Console\Command;

class BootstrapSuperadmin extends Command
{
    protected $signature = 'app:bootstrap-superadmin {chat_id? : Telegram chat/user ID to promote}';

    protected $description = 'Bootstrap first superadmin safely from configured TELEGRAM_ADMIN_ID list.';

    public function handle(): int
    {
        $chatIdArg = trim((string) ($this->argument('chat_id') ?? ''));

        $configuredIds = collect(explode(',', (string) env('TELEGRAM_ADMIN_ID', '')))
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '')
            ->values();

        if ($configuredIds->isEmpty()) {
            $this->error('TELEGRAM_ADMIN_ID is empty. Set it first (single ID or comma-separated IDs).');

            return self::FAILURE;
        }

        if (TelegramUser::where('level', 0)->exists()) {
            $this->warn('A superadmin already exists. Bootstrap is blocked to prevent accidental privilege escalation.');

            return self::FAILURE;
        }

        if ($chatIdArg !== '' && !$configuredIds->contains($chatIdArg)) {
            $this->error("Chat ID {$chatIdArg} is not listed in TELEGRAM_ADMIN_ID.");

            return self::FAILURE;
        }

        $targetChatIds = $chatIdArg !== '' ? collect([$chatIdArg]) : $configuredIds;

        $target = TelegramUser::query()
            ->whereIn('user_id', $targetChatIds->all())
            ->orderByRaw('CASE WHEN level = 2 THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first();

        if (!$target) {
            $this->error('No Telegram user found for configured/admin-specified chat ID. Open the bot and send /start first.');

            return self::FAILURE;
        }

        if ((int) $target->level === 0) {
            $this->info("Telegram user {$target->user_id} is already superadmin.");

            return self::SUCCESS;
        }

        $target->level = 0;
        $target->save();

        $this->info("Superadmin bootstrapped successfully for chat ID {$target->user_id}.");

        return self::SUCCESS;
    }
}
