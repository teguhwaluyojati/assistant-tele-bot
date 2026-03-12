<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionActivityService;
use App\Services\TransactionAuthorizationService;
use App\Services\TransactionCategoryService;

class UpdateTransactionAction
{
    public function __construct(
        private TransactionAuthorizationService $transactionAuthorizationService,
        private TransactionCategoryService $transactionCategoryService,
        private TransactionActivityService $transactionActivityService
    ) {
    }

    public function execute(?User $currentUser, Transaction $transaction, array $validated): array
    {
        if (!$this->transactionAuthorizationService->canManageTransaction($currentUser, $transaction)) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Unauthorized to update this transaction.',
            ];
        }

        $resolvedCategory = $this->transactionCategoryService->resolve(
            $validated['description'] ?? null,
            $validated['category'] ?? null,
            $validated['type']
        );

        $transaction->update([
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'description' => $resolvedCategory['description'],
            'category' => $resolvedCategory['category'],
            'category_source' => $resolvedCategory['category_source'],
            'category_confidence' => $resolvedCategory['category_confidence'],
        ]);

        $this->transactionActivityService->logUpdate($currentUser, $transaction, $validated);

        return [
            'ok' => true,
            'transaction' => $transaction,
        ];
    }
}
