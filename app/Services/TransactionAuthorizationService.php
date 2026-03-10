<?php

namespace App\Services;

use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Models\User;

class TransactionAuthorizationService
{
    public function linkedTelegramUser(?User $user): ?TelegramUser
    {
        if (!$user) {
            return null;
        }

        return $user->telegramUser;
    }

    public function linkedChatId(?User $user): ?int
    {
        $telegramUser = $this->linkedTelegramUser($user);

        return $telegramUser?->user_id ? (int) $telegramUser->user_id : null;
    }

    public function isAdmin(?User $user): bool
    {
        $telegramUser = $this->linkedTelegramUser($user);

        return $telegramUser?->isAdmin() ?? false;
    }

    public function canManageTransaction(?User $user, Transaction $transaction): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        $chatId = $this->linkedChatId($user);
        if (!$chatId) {
            return false;
        }

        return $chatId === (int) $transaction->user_id;
    }
}
