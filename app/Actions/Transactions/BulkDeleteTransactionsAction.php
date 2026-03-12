<?php

namespace App\Actions\Transactions;

use App\Models\User;
use App\Services\TransactionActivityService;
use App\Services\TransactionBulkDeleteService;

class BulkDeleteTransactionsAction
{
    public function __construct(
        private TransactionBulkDeleteService $transactionBulkDeleteService,
        private TransactionActivityService $transactionActivityService
    ) {
    }

    public function execute(?User $currentUser, array $ids): array
    {
        $resolution = $this->transactionBulkDeleteService->resolveAuthorizedTransactions($currentUser, $ids);

        if ($resolution['status'] === 'missing_telegram') {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Your account is not linked to a Telegram user.',
            ];
        }

        $transactionsToDelete = $resolution['transactions'];
        $deleteCount = $transactionsToDelete->count();

        if ($deleteCount === 0) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'No authorized transactions found to delete.',
            ];
        }

        $this->transactionBulkDeleteService->deleteTransactions($transactionsToDelete);
        $this->transactionActivityService->logBulkDelete(
            $currentUser,
            $deleteCount,
            $transactionsToDelete->pluck('id')->all()
        );

        return [
            'ok' => true,
            'deleted' => $deleteCount,
        ];
    }
}
