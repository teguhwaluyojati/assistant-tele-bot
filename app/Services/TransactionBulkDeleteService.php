<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TransactionBulkDeleteService
{
    public function __construct(private TransactionAuthorizationService $transactionAuthorizationService)
    {
    }

    public function resolveAuthorizedTransactions(?User $user, array $ids): array
    {
        $isAdmin = $this->transactionAuthorizationService->isAdmin($user);
        $query = Transaction::whereIn('id', $ids);

        if (!$isAdmin) {
            $chatId = $this->transactionAuthorizationService->linkedChatId($user);
            if (!$chatId) {
                return [
                    'status' => 'missing_telegram',
                    'transactions' => collect(),
                    'chat_id' => null,
                ];
            }

            $query->where('user_id', $chatId);
        }

        return [
            'status' => 'ok',
            'transactions' => $query->get(),
            'chat_id' => $this->transactionAuthorizationService->linkedChatId($user),
        ];
    }

    public function deleteTransactions(Collection $transactions): int
    {
        $ids = $transactions->pluck('id')->all();
        if (count($ids) === 0) {
            return 0;
        }

        return Transaction::whereIn('id', $ids)->delete();
    }
}
