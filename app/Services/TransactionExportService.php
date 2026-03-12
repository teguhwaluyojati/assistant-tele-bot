<?php

namespace App\Services;

use App\Exports\TransactionsExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionExportService
{
    public function buildContext(bool $isAdmin, ?int $chatId, ?string $startDate, ?string $endDate): array
    {
        return [
            'user_id' => $isAdmin ? null : $chatId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'file_name' => 'transactions-' . now()->format('Y-m-d-H-i-s') . '.xlsx',
        ];
    }

    public function download(?int $userId, bool $isAdmin, ?string $startDate, ?string $endDate, string $fileName): BinaryFileResponse
    {
        return Excel::download(
            new TransactionsExport($userId, $isAdmin, $startDate, $endDate),
            $fileName
        );
    }
}
