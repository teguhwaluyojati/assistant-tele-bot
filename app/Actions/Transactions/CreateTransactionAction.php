<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionActivityService;
use App\Services\TransactionAuthorizationService;
use App\Services\TransactionCategoryService;
use App\Services\TransactionPersistenceService;
use Illuminate\Support\Carbon;

class CreateTransactionAction
{
    public function __construct(
        private TransactionAuthorizationService $transactionAuthorizationService,
        private TransactionCategoryService $transactionCategoryService,
        private TransactionPersistenceService $transactionPersistenceService,
        private TransactionActivityService $transactionActivityService
    ) {
    }

    public function execute(?User $currentUser, array $validated): array
    {
        $telegramUser = $this->transactionAuthorizationService->linkedTelegramUser($currentUser);

        if (!$telegramUser) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'User not linked to Telegram account.',
            ];
        }

        if (empty($telegramUser->user_id)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Telegram account is not fully initialized. Please open the bot and send /start, then try again.',
            ];
        }

        $transactionTimestamp = isset($validated['transaction_date']) && $validated['transaction_date']
            ? Carbon::createFromFormat('Y-m-d\\TH:i', $validated['transaction_date'], config('app.timezone'))
            : now();

        $resolvedCategory = $this->transactionCategoryService->resolve(
            $validated['description'] ?? null,
            $validated['category'] ?? null,
            $validated['type']
        );

        $transaction = new Transaction([
            'user_id' => $telegramUser->user_id,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'description' => $resolvedCategory['description'],
            'category' => $resolvedCategory['category'],
            'category_source' => $resolvedCategory['category_source'],
            'category_confidence' => $resolvedCategory['category_confidence'],
        ]);

        $transaction->created_at = $transactionTimestamp;
        $transaction->updated_at = $transactionTimestamp;

        $this->transactionPersistenceService->saveNew($transaction);
        $this->transactionActivityService->logCreate($currentUser, $transaction);

        return [
            'ok' => true,
            'transaction' => $transaction,
        ];
    }
}
