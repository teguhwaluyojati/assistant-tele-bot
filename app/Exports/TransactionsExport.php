<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class TransactionsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private $userId;
    private $isAdmin;
    private $startDate;
    private $endDate;

    public function __construct($userId = null, $isAdmin = false, $startDate = null, $endDate = null)
    {
        $this->userId = $userId;
        $this->isAdmin = $isAdmin;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function query()
    {
        $query = Transaction::with('user:id,user_id,username,first_name,last_name')
            ->latest();

        // Apply date filter if provided
        if ($this->startDate && $this->endDate) {
            $startDate = Carbon::parse($this->startDate)->startOfDay();
            $endDate = Carbon::parse($this->endDate)->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Filter by user if not admin
        if (!$this->isAdmin && $this->userId) {
            $query->where('user_id', $this->userId);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Type',
            'Amount',
            'Description',
            'User',
        ];
    }

    public function map($transaction): array
    {
        $userName = $transaction->user 
            ? ($transaction->user->first_name ?? '') . ' ' . ($transaction->user->last_name ?? '')
            : 'Unknown';

        return [
            $transaction->created_at->format('Y-m-d H:i:s'),
            ucfirst($transaction->type),
            number_format($transaction->amount, 2, ',', '.'),
            $transaction->description ?? '-',
            trim($userName),
        ];
    }
}
