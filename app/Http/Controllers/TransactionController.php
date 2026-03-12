<?php

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Services\TransactionActivityService;
use App\Services\TransactionAuthorizationService;
use App\Services\TransactionBulkDeleteService;
use App\Services\TransactionCategoryService;
use App\Services\TransactionExportService;
use App\Services\TransactionPersistenceService;
use App\Services\TransactionQueryService;
use App\Traits\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TransactionCategoryService $transactionCategoryService,
        protected TransactionAuthorizationService $transactionAuthorizationService,
        protected TransactionQueryService $transactionQueryService,
        protected TransactionPersistenceService $transactionPersistenceService,
        protected TransactionActivityService $transactionActivityService,
        protected TransactionExportService $transactionExportService,
        protected TransactionBulkDeleteService $transactionBulkDeleteService
    ) {}

    public function getTransactions(Request $request)
    {
        try {
            $validated = $request->validate([
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'type' => ['nullable', 'in:all,income,expense'],
                'search' => ['nullable', 'string', 'max:255'],
                'start_date' => ['nullable', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
                'sort' => ['nullable', 'in:created_at,amount,type,description'],
                'direction' => ['nullable', 'in:asc,desc'],
            ]);

            $currentUser = auth()->user();
            $telegramUser = $this->transactionAuthorizationService->linkedTelegramUser($currentUser);

            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            $perPage = $validated['per_page'] ?? 15;
            $transactions = $this->transactionQueryService->paginateTransactions(
                $validated,
                $this->transactionAuthorizationService->isAdmin($currentUser),
                (int) $telegramUser->user_id,
                $perPage
            );

            return $this->successResponse($transactions, 'Transactions retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error retrieving transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving transactions.', 500);
        }
    }

    public function getTransactionsSummary(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => ['nullable', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            ]);

            [$startDate, $endDate] = $this->transactionQueryService->resolveRange(
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null,
                'month'
            );

            if ($startDate->gt($endDate)) {
                return $this->errorResponse('Start date must be before end date.', 422);
            }

            $telegramUserId = auth()->user()->telegram_user_id;

            if (!$telegramUserId) {
                return $this->errorResponse('Your account is not linked to a Telegram user.', 403);
            }

            $telegramUser = TelegramUser::find($telegramUserId);
            if (!$telegramUser) {
                return $this->errorResponse('Telegram user not found.', 404);
            }

            $chatId = $telegramUser->user_id;

            $summary = $this->transactionQueryService->buildSummary($startDate, $endDate, (int) $chatId);

            return $this->successResponse($summary, 'Transaction summary retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error retrieving transaction summary: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving summary.', 500);
        }
    }

    public function getDailyChart(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => ['nullable', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            ]);

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

            $chatId = null;

            $currentUser = auth()->user();
            if (!$this->transactionAuthorizationService->isAdmin($currentUser)) {
                $telegramUserId = $currentUser->telegram_user_id;

                if (!$telegramUserId) {
                    return $this->errorResponse('Your account is not linked to a Telegram user.', 403);
                }

                $telegramUser = TelegramUser::find($telegramUserId);
                if (!$telegramUser) {
                    return $this->errorResponse('Telegram user not found.', 404);
                }

                $chatId = $telegramUser->user_id;
            }

            $chartData = $this->transactionQueryService->buildDailyChart($startDate, $endDate, $chatId);

            return $this->successResponse($chartData, 'Daily chart data retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error retrieving daily chart: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving chart data.', 500);
        }
    }

    public function storeTransaction(Request $request)
    {
        try {
            $validated = $request->validate([
                'type' => ['required', 'in:income,expense'],
                'amount' => ['required', 'integer', 'min:1'],
                'transaction_date' => ['nullable', 'date_format:Y-m-d\\TH:i'],
                'description' => ['nullable', 'string', 'max:255'],
                'category' => ['nullable', 'string', 'max:100'],
            ]);

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
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
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

    public function updateTransaction(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'type' => ['required', 'in:income,expense'],
                'amount' => ['required', 'integer', 'min:1'],
                'description' => ['nullable', 'string', 'max:255'],
                'category' => ['nullable', 'string', 'max:100'],
            ]);

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
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
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

    public function bulkDeleteTransactions(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer|distinct|exists:transactions,id',
            ]);

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
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error bulk deleting transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while deleting transactions.', 500);
        }
    }

    public function exportTransactions(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => ['nullable', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            ]);

            $currentUser = auth()->user();
            $isAdmin = $this->transactionAuthorizationService->isAdmin($currentUser);

            $startDate = $validated['start_date'] ?? null;
            $endDate = $validated['end_date'] ?? null;

            if (!$isAdmin) {
                if (!$this->transactionAuthorizationService->linkedTelegramUser($currentUser)) {
                    return $this->errorResponse('User not linked to Telegram account.', 403);
                }
            }

            $chatId = $this->transactionAuthorizationService->linkedChatId($currentUser);
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
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error exporting transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while exporting transactions.', 500);
        }
    }
}
