<?php

namespace App\Http\Controllers;

use App\Exports\TransactionsExport;
use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Services\AutoCategoryService;
use App\Traits\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class TransactionController extends Controller
{
    use ApiResponse;

    public function __construct(protected AutoCategoryService $autoCategoryService)
    {
    }

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

            $query = Transaction::with('user:id,user_id,username,first_name,last_name')
                ->latest();

            $currentUser = auth()->user();
            $telegramUser = $currentUser->telegramUser;

            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            if (!$telegramUser->isAdmin()) {
                $query->where('user_id', $telegramUser->user_id);
            }

            if (!empty($validated['type']) && $validated['type'] !== 'all') {
                $query->where('type', $validated['type']);
            }

            if (!empty($validated['search'])) {
                $search = '%' . trim($validated['search']) . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', $search)
                        ->orWhereHas('user', function ($subQ) use ($search) {
                            $subQ->where('username', 'like', $search)
                                ->orWhere('first_name', 'like', $search)
                                ->orWhere('last_name', 'like', $search);
                        });
                });
            }

            if (!empty($validated['start_date'])) {
                $query->whereDate('created_at', '>=', $validated['start_date']);
            }

            if (!empty($validated['end_date'])) {
                $query->whereDate('created_at', '<=', $validated['end_date']);
            }

            $sortField = $validated['sort'] ?? 'created_at';
            $sortDirection = $validated['direction'] ?? 'desc';
            $query->orderBy($sortField, $sortDirection);

            $perPage = $validated['per_page'] ?? 15;
            $transactions = $query->paginate($perPage);

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

            $startDateInput = $validated['start_date'] ?? null;
            $endDateInput = $validated['end_date'] ?? null;

            if ($startDateInput || $endDateInput) {
                $startDate = $startDateInput
                    ? Carbon::parse($startDateInput)->startOfDay()
                    : now()->startOfMonth();
                $endDate = $endDateInput
                    ? Carbon::parse($endDateInput)->endOfDay()
                    : now()->endOfDay();
            } else {
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
            }

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

            $incomeQuery = Transaction::where('type', 'income')
                ->whereBetween('created_at', [$startDate, $endDate]);
            $expenseQuery = Transaction::where('type', 'expense')
                ->whereBetween('created_at', [$startDate, $endDate]);
            $countQuery = Transaction::whereBetween('created_at', [$startDate, $endDate]);

            $incomeQuery->where('user_id', $chatId);
            $expenseQuery->where('user_id', $chatId);
            $countQuery->where('user_id', $chatId);

            $totalIncome = $incomeQuery->sum('amount');
            $totalExpense = $expenseQuery->sum('amount');
            $balance = $totalIncome - $totalExpense;
            $totalTransactions = $countQuery->count();

            $summary = [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'balance' => $balance,
                'total_transactions' => $totalTransactions,
                'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            ];

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

            $startDateInput = $validated['start_date'] ?? null;
            $endDateInput = $validated['end_date'] ?? null;

            if ($startDateInput || $endDateInput) {
                $startDate = $startDateInput
                    ? Carbon::parse($startDateInput)->startOfDay()
                    : now()->startOfMonth();
                $endDate = $endDateInput
                    ? Carbon::parse($endDateInput)->endOfDay()
                    : now()->endOfDay();
            } else {
                $startDate = now()->subDays(6)->startOfDay();
                $endDate = now()->endOfDay();
            }

            if ($startDate->gt($endDate)) {
                return $this->errorResponse('Start date must be before end date.', 422);
            }

            if ($startDate->diffInDays($endDate) > 366) {
                return $this->errorResponse('Date range cannot exceed 366 days.', 422);
            }

            $chatId = null;

            if (!auth()->user()->isAdmin()) {
                $telegramUserId = auth()->user()->telegram_user_id;

                if (!$telegramUserId) {
                    return $this->errorResponse('Your account is not linked to a Telegram user.', 403);
                }

                $telegramUser = TelegramUser::find($telegramUserId);
                if (!$telegramUser) {
                    return $this->errorResponse('Telegram user not found.', 404);
                }

                $chatId = $telegramUser->user_id;
            }

            $labels = [];
            $incomeData = [];
            $expenseData = [];

            $cursor = $startDate->copy();
            $endDateDay = $endDate->copy()->startOfDay();

            while ($cursor->lte($endDateDay)) {
                $labels[] = $cursor->format('M d');

                $incomeQuery = Transaction::where('type', 'income')
                    ->whereDate('created_at', $cursor->toDateString());
                $expenseQuery = Transaction::where('type', 'expense')
                    ->whereDate('created_at', $cursor->toDateString());

                if ($chatId) {
                    $incomeQuery->where('user_id', $chatId);
                    $expenseQuery->where('user_id', $chatId);
                }

                $income = $incomeQuery->sum('amount');
                $expense = $expenseQuery->sum('amount');

                $incomeData[] = (int) $income;
                $expenseData[] = (int) $expense;

                $cursor->addDay();
            }

            $chartData = [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Income',
                        'data' => $incomeData,
                        'borderColor' => 'rgb(34, 197, 94)',
                        'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                        'fill' => true,
                    ],
                    [
                        'label' => 'Expense',
                        'data' => $expenseData,
                        'borderColor' => 'rgb(239, 68, 68)',
                        'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                        'fill' => true,
                    ],
                ],
            ];

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
            $telegramUser = $currentUser?->telegramUser;

            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            if (empty($telegramUser->user_id)) {
                return $this->errorResponse('Telegram account is not fully initialized. Please open the bot and send /start, then try again.', 422);
            }

            $transactionTimestamp = isset($validated['transaction_date']) && $validated['transaction_date']
                ? Carbon::createFromFormat('Y-m-d\\TH:i', $validated['transaction_date'], config('app.timezone'))
                : now();

            $description = trim((string) ($validated['description'] ?? ''));
            $manualCategory = trim((string) ($validated['category'] ?? ''));

            $category = null;
            $categorySource = null;
            $categoryConfidence = null;

            if ($manualCategory !== '') {
                $category = $manualCategory;
                $categorySource = 'manual';
                $categoryConfidence = 1.00;
            } elseif ($description !== '') {
                $inferredCategory = $this->autoCategoryService->infer($description, $validated['type']);
                if ($inferredCategory) {
                    $category = $inferredCategory['category'];
                    $categorySource = 'auto';
                    $categoryConfidence = $inferredCategory['confidence'];
                }
            }

            $transaction = new Transaction([
                'user_id' => $telegramUser->user_id,
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'description' => $description,
                'category' => $category,
                'category_source' => $categorySource,
                'category_confidence' => $categoryConfidence,
            ]);

            $transaction->created_at = $transactionTimestamp;
            $transaction->updated_at = $transactionTimestamp;

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

            try {
                activity()
                    ->causedBy($currentUser)
                    ->performedOn($transaction)
                    ->withProperties([
                        'transaction_id' => $transaction->id,
                        'owner_user_id' => $transaction->user_id,
                        'type' => $transaction->type,
                        'amount' => $transaction->amount,
                    ])
                    ->log('create_transaction');
            } catch (\Throwable $activityException) {
                Log::warning('Transaction created but activity log failed: ' . $activityException->getMessage(), [
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                ]);
            }

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

            $isAdmin = $currentUser->telegramUser && $currentUser->telegramUser->isAdmin();
            $isOwner = $currentUser->telegramUser && $currentUser->telegramUser->user_id === $transaction->user_id;

            if (!$isAdmin && !$isOwner) {
                return $this->errorResponse('Unauthorized to update this transaction.', 403);
            }

            $description = trim((string) ($validated['description'] ?? ''));
            $manualCategory = trim((string) ($validated['category'] ?? ''));

            $category = null;
            $categorySource = null;
            $categoryConfidence = null;

            if ($manualCategory !== '') {
                $category = $manualCategory;
                $categorySource = 'manual';
                $categoryConfidence = 1.00;
            } elseif ($description !== '') {
                $inferredCategory = $this->autoCategoryService->infer($description, $validated['type']);
                if ($inferredCategory) {
                    $category = $inferredCategory['category'];
                    $categorySource = 'auto';
                    $categoryConfidence = $inferredCategory['confidence'];
                }
            }

            $transaction->update([
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'description' => $description,
                'category' => $category,
                'category_source' => $categorySource,
                'category_confidence' => $categoryConfidence,
            ]);

            activity()
                ->causedBy($currentUser)
                ->performedOn($transaction)
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'owner_user_id' => $transaction->user_id,
                    'type' => $validated['type'],
                    'amount' => $validated['amount'],
                ])
                ->log('update_transaction');

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

            $isAdmin = $currentUser->telegramUser && $currentUser->telegramUser->isAdmin();
            $isOwner = $currentUser->telegramUser && $currentUser->telegramUser->user_id === $transaction->user_id;

            if (!$isAdmin && !$isOwner) {
                return $this->errorResponse('Unauthorized to delete this transaction.', 403);
            }

            $transaction->delete();

            activity()
                ->causedBy($currentUser)
                ->performedOn($transaction)
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'owner_user_id' => $transaction->user_id,
                ])
                ->log('delete_transaction');

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
            $isAdmin = $currentUser->telegramUser && $currentUser->telegramUser->isAdmin();

            $query = Transaction::whereIn('id', $validated['ids']);

            if (!$isAdmin) {
                $telegramUserId = $currentUser->telegram_user_id;
                if (!$telegramUserId) {
                    return $this->errorResponse('Your account is not linked to a Telegram user.', 403);
                }

                $telegramUser = TelegramUser::find($telegramUserId);
                if (!$telegramUser) {
                    return $this->errorResponse('Telegram user not found.', 404);
                }

                $query->where('user_id', $telegramUser->user_id);
            }

            $transactionsToDelete = $query->get();
            $deleteCount = $transactionsToDelete->count();

            if ($deleteCount === 0) {
                return $this->errorResponse('No authorized transactions found to delete.', 403);
            }

            Transaction::whereIn('id', $transactionsToDelete->pluck('id'))->delete();

            activity()
                ->causedBy($currentUser)
                ->withProperties([
                    'count' => $deleteCount,
                    'transaction_ids' => $transactionsToDelete->pluck('id')->all(),
                ])
                ->log('bulk_delete_transactions');

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
            $isAdmin = $currentUser->telegramUser && $currentUser->telegramUser->isAdmin();

            $startDate = $validated['start_date'] ?? null;
            $endDate = $validated['end_date'] ?? null;

            if (!$isAdmin) {
                if (!$currentUser->telegramUser) {
                    return $this->errorResponse('User not linked to Telegram account.', 403);
                }
            }

            $userId = $isAdmin ? null : $currentUser->telegramUser->user_id;

            $fileName = 'transactions-' . now()->format('Y-m-d-H-i-s') . '.xlsx';

            activity()
                ->causedBy($currentUser)
                ->withProperties([
                    'user_id' => $userId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'file' => $fileName,
                ])
                ->log('export_transactions');

            return Excel::download(
                new TransactionsExport($userId, $isAdmin, $startDate, $endDate),
                $fileName
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error exporting transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while exporting transactions.', 500);
        }
    }
}
