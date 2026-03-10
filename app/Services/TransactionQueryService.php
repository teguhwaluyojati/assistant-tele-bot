<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class TransactionQueryService
{
    public function paginateTransactions(array $validated, bool $isAdmin, int $chatId, int $perPage = 15): LengthAwarePaginator
    {
        $query = Transaction::with('user:id,user_id,username,first_name,last_name')
            ->latest();

        if (!$isAdmin) {
            $query->where('user_id', $chatId);
        }

        if (!empty($validated['type']) && $validated['type'] !== 'all') {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['search'])) {
            $search = '%' . trim((string) $validated['search']) . '%';
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

        return $query->paginate($perPage);
    }

    public function resolveRange(?string $startDateInput, ?string $endDateInput, string $fallback): array
    {
        if ($startDateInput || $endDateInput) {
            $startDate = $startDateInput
                ? Carbon::parse($startDateInput)->startOfDay()
                : now()->startOfMonth();
            $endDate = $endDateInput
                ? Carbon::parse($endDateInput)->endOfDay()
                : now()->endOfDay();

            return [$startDate, $endDate];
        }

        if ($fallback === 'last_7_days') {
            return [now()->subDays(6)->startOfDay(), now()->endOfDay()];
        }

        return [now()->startOfMonth(), now()->endOfMonth()];
    }

    public function buildSummary(Carbon $startDate, Carbon $endDate, int $chatId): array
    {
        $incomeQuery = Transaction::where('type', 'income')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('user_id', $chatId);

        $expenseQuery = Transaction::where('type', 'expense')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('user_id', $chatId);

        $countQuery = Transaction::whereBetween('created_at', [$startDate, $endDate])
            ->where('user_id', $chatId);

        $totalIncome = $incomeQuery->sum('amount');
        $totalExpense = $expenseQuery->sum('amount');

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'balance' => $totalIncome - $totalExpense,
            'total_transactions' => $countQuery->count(),
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
        ];
    }

    public function buildDailyChart(Carbon $startDate, Carbon $endDate, ?int $chatId = null): array
    {
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

            $incomeData[] = (int) $incomeQuery->sum('amount');
            $expenseData[] = (int) $expenseQuery->sum('amount');

            $cursor->addDay();
        }

        return [
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
    }
}
