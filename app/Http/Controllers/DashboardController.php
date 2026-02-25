<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\LoginModel;
use App\Models\TelegramUserCommand;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use App\Traits\ApiResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\StocksImport;
use App\Exports\UserCommandsExport;


class DashboardController extends Controller
{
    protected $model_login;
    use ApiResponse;

    private function requireAdmin()
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $telegramUser = $currentUser->telegramUser;
        if (!$telegramUser) {
            return $this->errorResponse('User not linked to Telegram account.', 403);
        }

        if (!$telegramUser->isAdmin()) {
            return $this->errorResponse('Forbidden.', 403);
        }

        return null;
    }

    public function getUsers()
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $users = TelegramUser::with('webUser:id,telegram_user_id,name,avatar')
            ->latest('last_interaction_at')
            ->paginate(15);

        return response()->json($users);
    }

    public function lastLogin()
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        Log::info('Cek: Fungsi lastLogin dipanggil');
       try{

        $data = (new LoginModel())->lastLogin();
        if(!$data){
            return $this->errorResponse('No login history found', 404);

            Log::error('No login history found for user: ' . auth()->user()->email);
        }

        return $this->successResponse($data, 'Login history retrieved successfully.');

       }catch(\Exception $e){
        Log::error('Error retrieving login history: ' . $e->getMessage());

        return $this->errorResponse('An error occurred while retrieving login history.', 500);
       }
    }

    public function upload(Request $request)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls'
        ]);

        try {
            Excel::import(new StocksImport, $request->file('file'));
            
            return $this->successResponse(null, 'Data saham berhasil diimport!');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal import: ' . $e->getMessage(), 500);
        }
    }

    public function getTransactions(Request $request)
    {
        try {
            $validated = $request->validate([
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'type' => ['nullable', 'in:all,income,expense'],
                'search' => ['nullable', 'string', 'max:255'],
                'sort' => ['nullable', 'in:created_at,amount,type,description'],
                'direction' => ['nullable', 'in:asc,desc'],
            ]);

            $query = \App\Models\Transaction::with('user:id,user_id,username,first_name,last_name')
                ->latest();

            $currentUser = auth()->user();
            $telegramUser = $currentUser->telegramUser;

            // If no telegram user linked, deny access
            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            // Filter by telegram user if not admin
            if (!$telegramUser->isAdmin()) {
                $query->where('user_id', $telegramUser->user_id);
            }

            // Type filter
            if (!empty($validated['type']) && $validated['type'] !== 'all') {
                $query->where('type', $validated['type']);
            }

            // Search filter
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

            // Sorting
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

            $chatId = null;
            
            // Filter by telegram user if not admin
            if (!auth()->user()->isAdmin()) {
                $telegramUserId = auth()->user()->telegram_user_id;
                
                if (!$telegramUserId) {
                    return $this->errorResponse('Your account is not linked to a Telegram user.', 403);
                }
                
                $telegramUser = \App\Models\TelegramUser::find($telegramUserId);
                if (!$telegramUser) {
                    return $this->errorResponse('Telegram user not found.', 404);
                }
                
                $chatId = $telegramUser->user_id;
            }
            
            $incomeQuery = \App\Models\Transaction::where('type', 'income')
                ->whereBetween('created_at', [$startDate, $endDate]);
            $expenseQuery = \App\Models\Transaction::where('type', 'expense')
                ->whereBetween('created_at', [$startDate, $endDate]);
            $countQuery = \App\Models\Transaction::whereBetween('created_at', [$startDate, $endDate]);
            
            if ($chatId) {
                $incomeQuery->where('user_id', $chatId);
                $expenseQuery->where('user_id', $chatId);
                $countQuery->where('user_id', $chatId);
            }
            
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
            
            // Filter by telegram user if not admin
            if (!auth()->user()->isAdmin()) {
                $telegramUserId = auth()->user()->telegram_user_id;
                
                if (!$telegramUserId) {
                    return $this->errorResponse('Your account is not linked to a Telegram user.', 403);
                }
                
                $telegramUser = \App\Models\TelegramUser::find($telegramUserId);
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
                
                $incomeQuery = \App\Models\Transaction::where('type', 'income')
                    ->whereDate('created_at', $cursor->toDateString());
                $expenseQuery = \App\Models\Transaction::where('type', 'expense')
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
                    ]
                ]
            ];

            return $this->successResponse($chartData, 'Daily chart data retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
            
        } catch (\Exception $e) {
            Log::error('Error retrieving daily chart: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving chart data.', 500);
        }
    }

    public function getUserDetail($userId)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        try {
            $user = TelegramUser::with('webUser:id,telegram_user_id,name,avatar')
                ->where('user_id', $userId)
                ->firstOrFail();
            
            $commands = TelegramUserCommand::where('user_id', $userId)
                ->latest()
                ->limit(50)
                ->get();

            return $this->successResponse([
                'user' => $user,
                'commands' => $commands
            ], 'User detail retrieved successfully.');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving user detail: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving user detail.', 500);
        }
    }

    public function getMyCommands()
    {
        try {
            $currentUser = auth()->user();
            if (!$currentUser) {
                return $this->errorResponse('Unauthorized.', 401);
            }

            $telegramUser = $currentUser->telegramUser;
            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            $commands = TelegramUserCommand::where('user_id', $telegramUser->user_id)
                ->latest()
                ->limit(10)
                ->get();

            return $this->successResponse($commands, 'User commands retrieved successfully.');
        } catch (\Exception $e) {
            Log::error('Error retrieving user commands: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving commands.', 500);
        }
    }

    public function getRecentCommands()
    {
        try {
            $currentUser = auth()->user();
            if (!$currentUser) {
                return $this->errorResponse('Unauthorized.', 401);
            }

            $telegramUser = $currentUser->telegramUser;
            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            $query = TelegramUserCommand::query();

            if ($telegramUser->isAdmin()) {
                $commands = $query
                    ->leftJoin('telegram_users', 'telegram_user_commands.user_id', '=', 'telegram_users.user_id')
                    ->select([
                        'telegram_user_commands.id',
                        'telegram_user_commands.command',
                        'telegram_user_commands.user_id',
                        'telegram_user_commands.created_at',
                        'telegram_users.username',
                        'telegram_users.first_name',
                        'telegram_users.last_name',
                    ])
                    ->latest('telegram_user_commands.created_at')
                    ->limit(10)
                    ->get();
            } else {
                $commands = $query
                    ->where('user_id', $telegramUser->user_id)
                    ->latest()
                    ->limit(10)
                    ->get();
            }

            return $this->successResponse($commands, 'Recent commands retrieved successfully.');
        } catch (\Exception $e) {
            Log::error('Error retrieving recent commands: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving commands.', 500);
        }
    }

    public function getUserCommands(Request $request)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        try {
            $validated = $request->validate([
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'search' => ['nullable', 'string', 'max:255'],
                'start_date' => ['nullable', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            ]);

            $query = TelegramUserCommand::query()
                ->leftJoin('telegram_users', 'telegram_user_commands.user_id', '=', 'telegram_users.user_id')
                ->select([
                    'telegram_user_commands.id',
                    'telegram_user_commands.command',
                    'telegram_user_commands.user_id',
                    'telegram_user_commands.created_at',
                    'telegram_users.username',
                    'telegram_users.first_name',
                    'telegram_users.last_name',
                ]);

            if (!empty($validated['search'])) {
                $keyword = '%' . trim($validated['search']) . '%';
                $query->where(function ($builder) use ($keyword) {
                    $builder
                        ->where('telegram_user_commands.command', 'like', $keyword)
                        ->orWhere('telegram_users.username', 'like', $keyword)
                        ->orWhere('telegram_users.first_name', 'like', $keyword)
                        ->orWhere('telegram_users.last_name', 'like', $keyword)
                        ->orWhere('telegram_user_commands.user_id', 'like', $keyword);
                });
            }

            if (!empty($validated['start_date'])) {
                $query->whereDate('telegram_user_commands.created_at', '>=', $validated['start_date']);
            }

            if (!empty($validated['end_date'])) {
                $query->whereDate('telegram_user_commands.created_at', '<=', $validated['end_date']);
            }

            $perPage = $validated['per_page'] ?? 15;
            $commands = $query
                ->latest('telegram_user_commands.created_at')
                ->paginate($perPage);

            return $this->successResponse($commands, 'User command list retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error retrieving user command list: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving user commands.', 500);
        }
    }

    public function exportUserCommands(Request $request)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        try {
            $validated = $request->validate([
                'search' => ['nullable', 'string', 'max:255'],
                'start_date' => ['nullable', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            ]);

            $search = $validated['search'] ?? null;
            $startDate = $validated['start_date'] ?? null;
            $endDate = $validated['end_date'] ?? null;
            $fileName = 'user-commands-' . now()->format('Y-m-d-H-i-s') . '.xlsx';

            activity()
                ->causedBy(auth()->user())
                ->withProperties([
                    'search' => $search,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'file' => $fileName,
                ])
                ->log('export_user_commands');

            return Excel::download(new UserCommandsExport($search, $startDate, $endDate), $fileName);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error exporting user commands: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while exporting user commands.', 500);
        }
    }

    public function getRecentLogins()
    {
        try {
            $currentUser = auth()->user();
            if (!$currentUser) {
                return $this->errorResponse('Unauthorized.', 401);
            }

            $telegramUser = $currentUser->telegramUser;
            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            $isAdmin = $telegramUser->isAdmin();
            $userEmail = $currentUser->email;

            // Use cached Eloquent model (60 second cache)
            $logins = LoginModel::getRecentLogins(
                limit: 10,
                isAdmin: $isAdmin,
                userEmail: $userEmail,
                cacheDuration: 60
            );

            return $this->successResponse($logins, 'Recent logins retrieved successfully.');
        } catch (\Exception $e) {
            Log::error('Error retrieving recent logins: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving logins.', 500);
        }
    }

    public function updateUserRole(Request $request, $userId)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        try {
            $validated = $request->validate([
                'level' => 'required|integer|in:1,2',
            ]);

            $user = TelegramUser::where('user_id', $userId)->firstOrFail();
            $currentUser = auth()->user();

            if ($currentUser && $currentUser->telegramUser && $currentUser->telegramUser->user_id === $user->user_id) {
                return $this->errorResponse('You cannot change your own role.', 403);
            }

            $user->level = $validated['level'];
            $user->save();

            activity()
                ->causedBy($currentUser)
                ->performedOn($user)
                ->withProperties([
                    'target_user_id' => $user->user_id,
                    'level' => $validated['level'],
                ])
                ->log('update_role');

            return $this->successResponse($user, 'User role updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error updating user role: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while updating user role.', 500);
        }
    }

    public function storeTransaction(Request $request)
    {
        try {
            $validated = $request->validate([
                'type' => ['required', 'in:income,expense'],
                'amount' => ['required', 'integer', 'min:1'],
                'description' => ['required', 'string', 'max:255'],
            ]);

            $currentUser = auth()->user();
            $telegramUser = $currentUser?->telegramUser;

            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            $transaction = \App\Models\Transaction::create([
                'user_id' => $telegramUser->user_id,
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
            ]);

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

            return $this->successResponse(
                $transaction->load('user:id,user_id,username,first_name,last_name'),
                'Transaction created successfully.'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error creating transaction: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while creating transaction.', 500);
        }
    }

    public function updateTransaction(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'type' => ['required', 'in:income,expense'],
                'amount' => ['required', 'integer', 'min:1'],
                'description' => ['required', 'string', 'max:255'],
            ]);

            $transaction = \App\Models\Transaction::findOrFail($id);
            $currentUser = auth()->user();

            $isAdmin = $currentUser->telegramUser && $currentUser->telegramUser->isAdmin();
            $isOwner = $currentUser->telegramUser && $currentUser->telegramUser->user_id === $transaction->user_id;

            if (!$isAdmin && !$isOwner) {
                return $this->errorResponse('Unauthorized to update this transaction.', 403);
            }

            $transaction->update($validated);

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

            return $this->successResponse($transaction->fresh(), 'Transaction updated successfully.');
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
            $transaction = \App\Models\Transaction::findOrFail($id);
            $currentUser = auth()->user();
            
            // Check authorization: Admin can delete any, User can only delete their own
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
            
            // Get transactions to delete
            $query = \App\Models\Transaction::whereIn('id', $validated['ids']);
            
            // If not admin, only allow deleting own transactions
            if (!$isAdmin) {
                $telegramUserId = $currentUser->telegram_user_id;
                if (!$telegramUserId) {
                    return $this->errorResponse('Your account is not linked to a Telegram user.', 403);
                }
                
                $telegramUser = \App\Models\TelegramUser::find($telegramUserId);
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
            
            // Delete transactions
            \App\Models\Transaction::whereIn('id', $transactionsToDelete->pluck('id'))->delete();

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

            // Non-admin users can only export their own transactions
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
                new \App\Exports\TransactionsExport($userId, $isAdmin, $startDate, $endDate),
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
