<?php

namespace App\Services;

use App\Models\TelegramUser;
use App\Models\User;

class TransactionAccessGuardService
{
    public function __construct(private TransactionAuthorizationService $transactionAuthorizationService)
    {
    }

    public function ensureListAccess(?User $user): array
    {
        $telegramUser = $this->transactionAuthorizationService->linkedTelegramUser($user);
        if (!$telegramUser) {
            return $this->error('User not linked to Telegram account.', 403);
        }

        return [
            'ok' => true,
            'telegram_user' => $telegramUser,
            'chat_id' => (int) $telegramUser->user_id,
        ];
    }

    public function ensureSummaryAccess(?User $user): array
    {
        $telegramUserId = $user?->telegram_user_id;
        if (!$telegramUserId) {
            return $this->error('Your account is not linked to a Telegram user.', 403);
        }

        $telegramUser = TelegramUser::find($telegramUserId);
        if (!$telegramUser) {
            return $this->error('Telegram user not found.', 404);
        }

        return [
            'ok' => true,
            'telegram_user' => $telegramUser,
            'chat_id' => (int) $telegramUser->user_id,
        ];
    }

    public function resolveDailyChartScope(?User $user): array
    {
        if ($this->transactionAuthorizationService->isAdmin($user)) {
            return [
                'ok' => true,
                'chat_id' => null,
            ];
        }

        return $this->ensureSummaryAccess($user);
    }

    public function ensureExportAccess(?User $user, bool $isAdmin): array
    {
        if ($isAdmin) {
            return [
                'ok' => true,
                'chat_id' => null,
            ];
        }

        $telegramUser = $this->transactionAuthorizationService->linkedTelegramUser($user);
        if (!$telegramUser) {
            return $this->error('User not linked to Telegram account.', 403);
        }

        return [
            'ok' => true,
            'chat_id' => (int) $telegramUser->user_id,
        ];
    }

    private function error(string $message, int $status): array
    {
        return [
            'ok' => false,
            'error' => [
                'message' => $message,
                'status' => $status,
            ],
        ];
    }
}
