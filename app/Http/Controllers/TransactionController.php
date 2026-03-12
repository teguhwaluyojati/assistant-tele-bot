<?php

namespace App\Http\Controllers;

use App\Http\Requests\Transactions\BulkDeleteTransactionsRequest;
use App\Http\Requests\Transactions\DailyChartRequest;
use App\Http\Requests\Transactions\ExportTransactionsRequest;
use App\Http\Requests\Transactions\GetTransactionsRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\TransactionSummaryRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionAccessGuardService;
use App\Services\TransactionActivityService;
use App\Services\TransactionAuthorizationService;
use App\Services\TransactionBulkDeleteService;
use App\Services\TransactionCategoryService;
use App\Services\TransactionExportService;
use App\Services\TransactionPersistenceService;
use App\Services\TransactionQueryService;
use App\Traits\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TransactionCategoryService $transactionCategoryService,
        protected TransactionAuthorizationService $transactionAuthorizationService,
        protected TransactionQueryService $transactionQueryService,
        protected TransactionAccessGuardService $transactionAccessGuardService,
        protected TransactionPersistenceService $transactionPersistenceService,
        protected TransactionActivityService $transactionActivityService,
        protected TransactionExportService $transactionExportService,
        protected TransactionBulkDeleteService $transactionBulkDeleteService
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
            $telegramUser = $this->transactionAuthorizationService->linkedTelegramUser($currentUser);

            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            if (empty($telegramUser->user_id)) {
                return $this->errorResponse('Telegram account is not fully initialized. Please open the bot and send /start, then try again.', 422);
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

            if (!$this->transactionAuthorizationService->canManageTransaction($currentUser, $transaction)) {
                return $this->errorResponse('Unauthorized to update this transaction.', 403);
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

            if (!$this->transactionAuthorizationService->canManageTransaction($currentUser, $transaction)) {
                return $this->errorResponse('Unauthorized to delete this transaction.', 403);
            }

            $transaction->delete();

            $this->transactionActivityService->logDelete($currentUser, $transaction);

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
            $resolution = $this->transactionBulkDeleteService->resolveAuthorizedTransactions(
                $currentUser,
                $validated['ids']
            );

            if ($resolution['status'] === 'missing_telegram') {
                return $this->errorResponse('Your account is not linked to a Telegram user.', 403);
            }

            $transactionsToDelete = $resolution['transactions'];
            $deleteCount = $transactionsToDelete->count();

            if ($deleteCount === 0) {
                return $this->errorResponse('No authorized transactions found to delete.', 403);
            }

            $this->transactionBulkDeleteService->deleteTransactions($transactionsToDelete);

            $this->transactionActivityService->logBulkDelete(
                $currentUser,
                $deleteCount,
                $transactionsToDelete->pluck('id')->all()
            );

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
            $isAdmin = $this->transactionAuthorizationService->isAdmin($currentUser);

            $startDate = $validated['start_date'] ?? null;
            $endDate = $validated['end_date'] ?? null;

            $access = $this->transactionAccessGuardService->ensureExportAccess($currentUser, $isAdmin);
            if (!$access['ok']) {
                return $this->errorResponse($access['error']['message'], $access['error']['status']);
            }

            $chatId = $access['chat_id'];
            $exportContext = $this->transactionExportService->buildContext($isAdmin, $chatId, $startDate, $endDate);

            $this->transactionActivityService->logExport(
                $currentUser,
                $exportContext['user_id'],
                $exportContext['start_date'],
                $exportContext['end_date'],
                $exportContext['file_name']
            );

            return $this->transactionExportService->download(
                $exportContext['user_id'],
                $isAdmin,
                $exportContext['start_date'],
                $exportContext['end_date'],
                $exportContext['file_name']
            );
        } catch (\Exception $e) {
            Log::error('Error exporting transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while exporting transactions.', 500);
        }
    }
}
