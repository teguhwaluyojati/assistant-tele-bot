<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionActivityService;
use App\Services\TransactionAuthorizationService;

class DeleteTransactionAction
{
    public function __construct(
        private TransactionAuthorizationService $transactionAuthorizationService,
        private TransactionActivityService $transactionActivityService
    ) {
    }

    public function execute(?User $currentUser, Transaction $transaction): array
    {
        if (!$this->transactionAuthorizationService->canManageTransaction($currentUser, $transaction)) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Unauthorized to delete this transaction.',
            ];
        }

        $transaction->delete();
        $this->transactionActivityService->logDelete($currentUser, $transaction);

        return [
            'ok' => true,
        ];
    }
}
