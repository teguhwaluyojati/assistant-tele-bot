<?php

namespace Tests\Unit;

use App\Models\LoginHistory;
use App\Models\LoginModel;
use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginModelTransactionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_history_login_returns_records_for_authenticated_user(): void
    {
        Cache::flush();

        $user = User::factory()->create(['email' => 'history@example.com']);
        $this->actingAs($user);

        LoginHistory::factory()->create(['email' => 'history@example.com']);
        LoginHistory::factory()->create(['email' => 'history@example.com']);
        LoginHistory::factory()->create(['email' => 'other@example.com']);

        $records = (new LoginModel())->historyLogin();

        $this->assertCount(2, $records);
        $this->assertTrue($records->every(fn ($row) => $row->email === 'history@example.com'));
    }

    public function test_last_login_returns_latest_record(): void
    {
        Cache::flush();

        $user = User::factory()->create(['email' => 'latest@example.com']);
        $this->actingAs($user);

        LoginHistory::factory()->create([
            'email' => 'latest@example.com',
            'created_at' => now()->subHour(),
        ]);
        $latest = LoginHistory::factory()->create([
            'email' => 'latest@example.com',
            'created_at' => now(),
        ]);

        $record = (new LoginModel())->lastLogin();

        $this->assertNotNull($record);
        $this->assertSame($latest->id, $record->id);
    }

    public function test_get_recent_logins_for_admin_returns_limited_list(): void
    {
        Cache::flush();

        LoginHistory::factory()->create([
            'email' => 'old@example.com',
            'created_at' => '2099-02-01 08:00:00',
        ]);
        LoginHistory::factory()->create([
            'email' => 'mid@example.com',
            'created_at' => '2099-02-01 09:00:00',
        ]);
        LoginHistory::factory()->create([
            'email' => 'new@example.com',
            'created_at' => '2099-02-01 10:00:00',
        ]);

        $records = LoginModel::getRecentLogins(limit: 2, isAdmin: true, cacheDuration: 60);

        $this->assertCount(2, $records);
        $this->assertSame('new@example.com', $records[0]->email);
        $this->assertSame('mid@example.com', $records[1]->email);
    }

    public function test_get_recent_logins_for_non_admin_returns_latest_own(): void
    {
        Cache::flush();

        LoginHistory::factory()->create([
            'email' => 'member@example.com',
            'created_at' => now()->subHours(2),
        ]);
        LoginHistory::factory()->create([
            'email' => 'member@example.com',
            'created_at' => now(),
        ]);
        LoginHistory::factory()->create([
            'email' => 'other@example.com',
            'created_at' => now()->addMinute(),
        ]);

        $records = LoginModel::getRecentLogins(limit: 10, isAdmin: false, userEmail: 'member@example.com', cacheDuration: 60);

        $this->assertCount(1, $records);
        $this->assertSame('member@example.com', $records[0]->email);
    }

    public function test_daily_expenses_returns_only_yesterday_expense_rows(): void
    {
        $telegramUser = TelegramUser::factory()->create();

        $expected = Transaction::factory()->create([
            'user_id' => $telegramUser->user_id,
            'type' => 'expense',
            'created_at' => now()->subDay()->setTime(10, 0),
        ]);

        Transaction::factory()->create([
            'user_id' => $telegramUser->user_id,
            'type' => 'income',
            'created_at' => now()->subDay()->setTime(11, 0),
        ]);

        Transaction::factory()->create([
            'user_id' => $telegramUser->user_id,
            'type' => 'expense',
            'created_at' => now()->setTime(12, 0),
        ]);

        $rows = (new Transaction())->DailyExpenses($telegramUser->user_id);

        $this->assertCount(1, $rows);
        $this->assertSame($expected->id, $rows[0]->id);
    }
}
