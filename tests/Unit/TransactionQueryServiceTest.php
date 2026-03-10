<?php

namespace Tests\Unit;

use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Services\TransactionQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TransactionQueryServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_resolve_range_uses_month_fallback_when_no_input(): void
    {
        Carbon::setTestNow('2026-03-10 10:00:00');

        $service = new TransactionQueryService();
        [$startDate, $endDate] = $service->resolveRange(null, null, 'month');

        $this->assertSame('2026-03-01 00:00:00', $startDate->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-31 23:59:59', $endDate->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_paginate_transactions_scopes_non_admin_to_chat_id(): void
    {
        $service = new TransactionQueryService();

        TelegramUser::factory()->create(['user_id' => 1111]);
        TelegramUser::factory()->create(['user_id' => 2222]);

        Transaction::factory()->create([
            'user_id' => 1111,
            'type' => 'expense',
            'amount' => 10000,
            'description' => 'makan siang',
        ]);

        Transaction::factory()->create([
            'user_id' => 2222,
            'type' => 'expense',
            'amount' => 20000,
            'description' => 'makan malam',
        ]);

        $result = $service->paginateTransactions([], false, 1111, 15);

        $this->assertCount(1, $result->items());
        $this->assertSame(1111, (int) $result->items()[0]->user_id);
    }

    public function test_build_summary_returns_expected_totals(): void
    {
        $service = new TransactionQueryService();

        TelegramUser::factory()->create(['user_id' => 3333]);

        Transaction::factory()->create([
            'user_id' => 3333,
            'type' => 'income',
            'amount' => 50000,
            'created_at' => '2026-03-03 10:00:00',
            'updated_at' => '2026-03-03 10:00:00',
        ]);

        Transaction::factory()->create([
            'user_id' => 3333,
            'type' => 'expense',
            'amount' => 20000,
            'created_at' => '2026-03-04 11:00:00',
            'updated_at' => '2026-03-04 11:00:00',
        ]);

        $summary = $service->buildSummary(
            Carbon::parse('2026-03-01')->startOfDay(),
            Carbon::parse('2026-03-10')->endOfDay(),
            3333
        );

        $this->assertSame(50000, (int) $summary['total_income']);
        $this->assertSame(20000, (int) $summary['total_expense']);
        $this->assertSame(30000, (int) $summary['balance']);
        $this->assertSame(2, (int) $summary['total_transactions']);
    }

    public function test_build_daily_chart_returns_expected_label_count(): void
    {
        $service = new TransactionQueryService();

        TelegramUser::factory()->create(['user_id' => 4444]);

        Transaction::factory()->create([
            'user_id' => 4444,
            'type' => 'income',
            'amount' => 10000,
            'created_at' => '2026-03-01 08:00:00',
            'updated_at' => '2026-03-01 08:00:00',
        ]);

        Transaction::factory()->create([
            'user_id' => 4444,
            'type' => 'expense',
            'amount' => 4000,
            'created_at' => '2026-03-02 09:00:00',
            'updated_at' => '2026-03-02 09:00:00',
        ]);

        $chart = $service->buildDailyChart(
            Carbon::parse('2026-03-01')->startOfDay(),
            Carbon::parse('2026-03-03')->endOfDay(),
            4444
        );

        $this->assertCount(3, $chart['labels']);
        $this->assertCount(2, $chart['datasets']);
        $this->assertSame([10000, 0, 0], $chart['datasets'][0]['data']);
        $this->assertSame([0, 4000, 0], $chart['datasets'][1]['data']);
    }
}
