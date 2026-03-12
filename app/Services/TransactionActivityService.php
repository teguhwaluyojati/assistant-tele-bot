<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TransactionActivityService
{
    public function logCreate(?User $actor, Transaction $transaction): void
    {
        try {
            activity()
                ->causedBy($actor)
                ->performedOn($transaction)
                ->withProperties($this->createPayload($transaction))
                ->log('create_transaction');
        } catch (\Throwable $activityException) {
            Log::warning('Transaction created but activity log failed: ' . $activityException->getMessage(), [
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
            ]);
        }
    }

    public function logUpdate(?User $actor, Transaction $transaction, array $validated): void
    {
        activity()
            ->causedBy($actor)
            ->performedOn($transaction)
            ->withProperties([
                'transaction_id' => $transaction->id,
                'owner_user_id' => $transaction->user_id,
                'type' => $validated['type'],
                'amount' => $validated['amount'],
            ])
            ->log('update_transaction');
    }

    public function logDelete(?User $actor, Transaction $transaction): void
    {
        activity()
            ->causedBy($actor)
            ->performedOn($transaction)
            ->withProperties([
                'transaction_id' => $transaction->id,
                'owner_user_id' => $transaction->user_id,
            ])
            ->log('delete_transaction');
    }

    public function logBulkDelete(?User $actor, int $deleteCount, array $transactionIds): void
    {
        activity()
            ->causedBy($actor)
            ->withProperties([
                'count' => $deleteCount,
                'transaction_ids' => $transactionIds,
            ])
            ->log('bulk_delete_transactions');
    }

    public function logExport(?User $actor, ?int $userId, ?string $startDate, ?string $endDate, string $fileName): void
    {
        activity()
            ->causedBy($actor)
            ->withProperties([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'file' => $fileName,
            ])
            ->log('export_transactions');
    }

    private function createPayload(Transaction $transaction): array
    {
        return [
            'transaction_id' => $transaction->id,
            'owner_user_id' => $transaction->user_id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
        ];
    }
}
