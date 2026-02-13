<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\LoginModel;
use Illuminate\Support\Facades\Log;
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
            $transactions = \App\Models\Transaction::with('user:id,user_id,username,first_name,last_name')
                ->latest()
                ->paginate(15);

            return $this->successResponse($transactions, 'Transactions retrieved successfully.');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving transactions: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving transactions.', 500);
        }
    }

    public function getTransactionsSummary()
    {
        try {
            $currentMonth = now()->startOfMonth();
            
            $totalIncome = \App\Models\Transaction::where('type', 'income')
                ->where('created_at', '>=', $currentMonth)
                ->sum('amount');
            
            $totalExpense = \App\Models\Transaction::where('type', 'expense')
                ->where('created_at', '>=', $currentMonth)
                ->sum('amount');
            
            $balance = $totalIncome - $totalExpense;
            
            $totalTransactions = \App\Models\Transaction::where('created_at', '>=', $currentMonth)
                ->count();

            $summary = [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'balance' => $balance,
                'total_transactions' => $totalTransactions,
                'period' => now()->format('F Y')
            ];

            return $this->successResponse($summary, 'Transaction summary retrieved successfully.');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving transaction summary: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving summary.', 500);
        }
    }

}
