<?php

namespace Tests\Unit;

use App\Services\TransactionExportService;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class TransactionExportServiceTest extends TestCase
{
    public function test_build_context_returns_expected_values_for_non_admin(): void
    {
        $service = new TransactionExportService();

        $context = $service->buildContext(false, 12345, '2026-03-01', '2026-03-10');

        $this->assertSame(12345, $context['user_id']);
        $this->assertSame('2026-03-01', $context['start_date']);
        $this->assertSame('2026-03-10', $context['end_date']);
        $this->assertStringStartsWith('transactions-', $context['file_name']);
        $this->assertStringEndsWith('.xlsx', $context['file_name']);
    }

    public function test_download_delegates_to_excel_facade(): void
    {
        $service = new TransactionExportService();
        $expectedResponse = $this->createMock(BinaryFileResponse::class);

        Excel::shouldReceive('download')
            ->once()
            ->andReturn($expectedResponse);

        $actualResponse = $service->download(12345, false, '2026-03-01', '2026-03-10', 'transactions-test.xlsx');

        $this->assertSame($expectedResponse, $actualResponse);
    }
}
