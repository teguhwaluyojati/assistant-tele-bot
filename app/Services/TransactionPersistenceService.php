<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TransactionPersistenceService
{
    public function saveNew(Transaction $transaction): void
    {
        try {
            $transaction->save();
        } catch (QueryException $e) {
            $dbMessage = strtolower((string) ($e->errorInfo[2] ?? $e->getMessage()));
            $isDuplicateTransactionPk = str_contains($dbMessage, 'transactions_pkey')
                || str_contains($dbMessage, 'duplicate key value violates unique constraint');

            if (!$isDuplicateTransactionPk) {
                throw $e;
            }

            DB::statement(
                "SELECT setval(pg_get_serial_sequence('transactions', 'id'), COALESCE((SELECT MAX(id) FROM transactions), 1), true)"
            );

            $transaction->save();
        }
    }
}
