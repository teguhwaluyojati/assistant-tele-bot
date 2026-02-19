<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\LoginModel;
use App\Models\TelegramUserCommand;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use App\Traits\ApiResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\StocksImport;


class DashboardController extends Controller
{
    protected $model_login;
    use ApiResponse;

    public function __construct()
    {
        $this->model_login = new LoginModel();
    }
    public function getUsers()
    {
        $users = TelegramUser::latest('last_interaction_at')->paginate(15);

        return response()->json($users);
    }

    public function lastLogin()
    {
        Log::info('Cek: Fungsi lastLogin dipanggil');
       try{

        $data = $this->model_login->lastLogin();
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

    public function getTransactions()
    {
        try {
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

            $transactions = $query->paginate(15);

            return $this->successResponse($transactions, 'Transactions retrieved successfully.');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving transactions.', 500);
        }
    }

    public function getTransactionsSummary(Request $request)
    {
        try {
            $startDateInput = $request->query('start_date');
            $endDateInput = $request->query('end_date');

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
            
        } catch (\Exception $e) {
            Log::error('Error retrieving transaction summary: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving summary.', 500);
        }
    }

    public function getDailyChart(Request $request)
    {
        try {
            $startDateInput = $request->query('start_date');
            $endDateInput = $request->query('end_date');

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
            
        } catch (\Exception $e) {
            Log::error('Error retrieving daily chart: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving chart data.', 500);
        }
    }

    public function getUserDetail($userId)
    {
        try {
            $user = TelegramUser::where('user_id', $userId)->firstOrFail();
            
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
                'ids.*' => 'required|integer',
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
            
            return $this->successResponse(
                ['deleted' => $deleteCount],
                "{$deleteCount} transaction(s) deleted successfully."
            );
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed: ' . json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            Log::error('Error bulk deleting transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while deleting transactions.', 500);
        }
    }

    public function exportTransactions(Request $request)
    {
        try {
            $currentUser = auth()->user();
            $isAdmin = $currentUser->telegramUser && $currentUser->telegramUser->isAdmin();
            
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // Non-admin users can only export their own transactions
            $userId = $isAdmin ? null : $currentUser->telegramUser->user_id;

            $fileName = 'transactions-' . now()->format('Y-m-d-H-i-s') . '.xlsx';

            return Excel::download(
                new \App\Exports\TransactionsExport($userId, $isAdmin, $startDate, $endDate),
                $fileName
            );

        } catch (\Exception $e) {
            Log::error('Error exporting transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while exporting transactions.', 500);
        }
    }

}
