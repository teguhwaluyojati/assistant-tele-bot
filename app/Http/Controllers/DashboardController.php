<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\LoginModel;
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

            // Filter by telegram user if not admin
            if (auth()->user()->telegramUser && !auth()->user()->telegramUser->isAdmin()) {
                $query->where('user_id', auth()->user()->telegramUser->user_id);
            }
            // If no telegram user linked, still show all for now (can be restricted later)

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

}
