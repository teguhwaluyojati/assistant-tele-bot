<?php

namespace App\Actions\Transactions;

use App\Models\User;
use App\Services\TransactionAccessGuardService;
use App\Services\TransactionActivityService;
use App\Services\TransactionAuthorizationService;
use App\Services\TransactionExportService;

class ExportTransactionsAction
{
    public function __construct(
        private TransactionAuthorizationService $transactionAuthorizationService,
        private TransactionAccessGuardService $transactionAccessGuardService,
        private TransactionExportService $transactionExportService,
        private TransactionActivityService $transactionActivityService
    ) {
    }

    public function execute(?User $currentUser, ?string $startDate, ?string $endDate): array
    {
        $isAdmin = $this->transactionAuthorizationService->isAdmin($currentUser);
        $access = $this->transactionAccessGuardService->ensureExportAccess($currentUser, $isAdmin);

        if (!$access['ok']) {
            return [
                'ok' => false,
                'status' => $access['error']['status'],
                'message' => $access['error']['message'],
            ];
        }

        $exportContext = $this->transactionExportService->buildContext($isAdmin, $access['chat_id'], $startDate, $endDate);

        $this->transactionActivityService->logExport(
            $currentUser,
            $exportContext['user_id'],
            $exportContext['start_date'],
            $exportContext['end_date'],
            $exportContext['file_name']
        );

        $response = $this->transactionExportService->download(
            $exportContext['user_id'],
            $isAdmin,
            $exportContext['start_date'],
            $exportContext['end_date'],
            $exportContext['file_name']
        );

        return [
            'ok' => true,
            'response' => $response,
        ];
    }
}
