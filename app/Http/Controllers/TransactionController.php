<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\BulkDeleteTransactionsAction;
use App\Actions\Transactions\CreateTransactionAction;
use App\Actions\Transactions\DeleteTransactionAction;
use App\Actions\Transactions\ExportTransactionsAction;
use App\Actions\Transactions\UpdateTransactionAction;
use App\Http\Requests\Transactions\BulkDeleteTransactionsRequest;
use App\Http\Requests\Transactions\DailyChartRequest;
use App\Http\Requests\Transactions\ExportTransactionsRequest;
use App\Http\Requests\Transactions\GetTransactionsRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\TransactionSummaryRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionAccessGuardService;
use App\Services\TransactionAuthorizationService;
use App\Services\TransactionQueryService;
use App\Traits\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TransactionAuthorizationService $transactionAuthorizationService,
        protected TransactionQueryService $transactionQueryService,
        protected TransactionAccessGuardService $transactionAccessGuardService,
        protected CreateTransactionAction $createTransactionAction,
        protected UpdateTransactionAction $updateTransactionAction,
        protected DeleteTransactionAction $deleteTransactionAction,
        protected BulkDeleteTransactionsAction $bulkDeleteTransactionsAction,
        protected ExportTransactionsAction $exportTransactionsAction
    ) {}

    public function getTransactions(GetTransactionsRequest $request)
    {
        try {
            $validated = $request->validated();

            $currentUser = auth()->user();
            $access = $this->transactionAccessGuardService->ensureListAccess($currentUser);
            if (!$access['ok']) {
                return $this->errorResponse($access['error']['message'], $access['error']['status']);
            }

            $perPage = $validated['per_page'] ?? 15;
            $transactions = $this->transactionQueryService->paginateTransactions(
                $validated,
                $this->transactionAuthorizationService->isAdmin($currentUser),
                (int) $access['chat_id'],
                $perPage
            );

            return $this->successResponse($transactions, 'Transactions retrieved successfully.');
        } catch (\Exception $e) {
            Log::error('Error retrieving transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving transactions.', 500);
        }
    }

    public function getTransactionsSummary(TransactionSummaryRequest $request)
    {
        try {
            $validated = $request->validated();

            [$startDate, $endDate] = $this->transactionQueryService->resolveRange(
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null,
                'month'
            );

            if ($startDate->gt($endDate)) {
                return $this->errorResponse('Start date must be before end date.', 422);
            }

            $currentUser = auth()->user();
            $access = $this->transactionAccessGuardService->ensureSummaryAccess($currentUser);
            if (!$access['ok']) {
                return $this->errorResponse($access['error']['message'], $access['error']['status']);
            }

            $summary = $this->transactionQueryService->buildSummary($startDate, $endDate, (int) $access['chat_id']);

            return $this->successResponse($summary, 'Transaction summary retrieved successfully.');
        } catch (\Exception $e) {
            Log::error('Error retrieving transaction summary: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving summary.', 500);
        }
    }

    public function getDailyChart(DailyChartRequest $request)
    {
        try {
            $validated = $request->validated();

            [$startDate, $endDate] = $this->transactionQueryService->resolveRange(
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null,
                'last_7_days'
            );

            if ($startDate->gt($endDate)) {
                return $this->errorResponse('Start date must be before end date.', 422);
            }

            if ($startDate->diffInDays($endDate) > 366) {
                return $this->errorResponse('Date range cannot exceed 366 days.', 422);
            }

            $currentUser = auth()->user();
            $scope = $this->transactionAccessGuardService->resolveDailyChartScope($currentUser);
            if (!$scope['ok']) {
                return $this->errorResponse($scope['error']['message'], $scope['error']['status']);
            }

            $chartData = $this->transactionQueryService->buildDailyChart($startDate, $endDate, $scope['chat_id']);

            return $this->successResponse($chartData, 'Daily chart data retrieved successfully.');
        } catch (\Exception $e) {
            Log::error('Error retrieving daily chart: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving chart data.', 500);
        }
    }

    public function storeTransaction(StoreTransactionRequest $request)
    {
        try {
            $validated = $request->validated();
            $currentUser = auth()->user();
            $result = $this->createTransactionAction->execute($currentUser, $validated);

            if (!$result['ok']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            $transaction = $result['transaction'];

            return $this->successResponse(
                $transaction->load('user:id,user_id,username,first_name,last_name'),
                'Transaction created successfully.'
            );
        } catch (QueryException $e) {
            Log::error('Database error creating transaction: ' . $e->getMessage(), [
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_code' => $e->errorInfo[1] ?? null,
                'db_detail' => $e->errorInfo[2] ?? null,
                'user_id' => auth()->id(),
            ]);

            return $this->errorResponse('Transaction failed due to database constraint. Please verify your Telegram account linkage and try again.', 422);
        } catch (\Exception $e) {
            Log::error('Error creating transaction: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'payload' => $request->only(['type', 'amount', 'transaction_date', 'description', 'category']),
            ]);
            return $this->errorResponse('An error occurred while creating transaction.', 500);
        }
    }

    public function updateTransaction(UpdateTransactionRequest $request, $id)
    {
        try {
            $validated = $request->validated();

            $transaction = Transaction::findOrFail($id);
            $currentUser = auth()->user();

            $result = $this->updateTransactionAction->execute($currentUser, $transaction, $validated);
            if (!$result['ok']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            return $this->successResponse(
                $transaction->fresh()->load('user:id,user_id,username,first_name,last_name'),
                'Transaction updated successfully.'
            );
        } catch (\Exception $e) {
            Log::error('Error updating transaction: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while updating transaction.', 500);
        }
    }

    public function deleteTransaction($id)
    {
        try {
            $transaction = Transaction::findOrFail($id);
            $currentUser = auth()->user();

            $result = $this->deleteTransactionAction->execute($currentUser, $transaction);
            if (!$result['ok']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            return $this->successResponse(null, 'Transaction deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting transaction: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while deleting transaction.', 500);
        }
    }

    public function bulkDeleteTransactions(BulkDeleteTransactionsRequest $request)
    {
        try {
            $validated = $request->validated();

            $currentUser = auth()->user();
            $result = $this->bulkDeleteTransactionsAction->execute($currentUser, $validated['ids']);
            if (!$result['ok']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            $deleteCount = $result['deleted'];

            return $this->successResponse(
                ['deleted' => $deleteCount],
                "{$deleteCount} transaction(s) deleted successfully."
            );
        } catch (\Exception $e) {
            Log::error('Error bulk deleting transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while deleting transactions.', 500);
        }
    }

    public function exportTransactions(ExportTransactionsRequest $request)
    {
        try {
            $validated = $request->validated();

            $currentUser = auth()->user();
            $startDate = $validated['start_date'] ?? null;
            $endDate = $validated['end_date'] ?? null;

            $result = $this->exportTransactionsAction->execute($currentUser, $startDate, $endDate);
            if (!$result['ok']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            return $result['response'];
        } catch (\Exception $e) {
            Log::error('Error exporting transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while exporting transactions.', 500);
        }
    }
}
