<?php

namespace Tests\Feature;

use App\Models\TelegramUser;
use App\Models\TelegramUserCommand;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_get_transactions_summary(): void
    {
        [$adminUser, $adminTelegramUser] = $this->createUserWithTelegram(level: 1);
        $startDate = '2099-01-10';
        $endDate = '2099-01-10';

        Transaction::factory()->create([
            'user_id' => $adminTelegramUser->user_id,
            'type' => 'income',
            'amount' => 3000,
            'created_at' => '2099-01-10 10:00:00',
        ]);
        Transaction::factory()->create([
            'user_id' => $adminTelegramUser->user_id,
            'type' => 'expense',
            'amount' => 1000,
            'created_at' => '2099-01-10 11:00:00',
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->getJson("/api/transactions/summary?start_date={$startDate}&end_date={$endDate}");

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertSame(3000, (int) $data['total_income']);
        $this->assertSame(1000, (int) $data['total_expense']);
        $this->assertSame(2000, (int) $data['balance']);
    }

    public function test_non_admin_gets_only_own_transactions_summary(): void
    {
        [$memberUser, $memberTelegramUser] = $this->createUserWithTelegram(level: 2);
        $startDate = '2099-01-11';
        $endDate = '2099-01-11';

        Transaction::factory()->create([
            'user_id' => $memberTelegramUser->user_id,
            'type' => 'income',
            'amount' => 5000,
            'created_at' => '2099-01-11 10:00:00',
        ]);
        Transaction::factory()->create([
            'user_id' => $memberTelegramUser->user_id,
            'type' => 'expense',
            'amount' => 1500,
            'created_at' => '2099-01-11 11:00:00',
        ]);

        $otherTelegramUser = TelegramUser::factory()->create();
        Transaction::factory()->create([
            'user_id' => $otherTelegramUser->user_id,
            'type' => 'income',
            'amount' => 9000,
            'created_at' => '2099-01-11 12:00:00',
        ]);

        Sanctum::actingAs($memberUser);

        $response = $this->getJson("/api/transactions/summary?start_date={$startDate}&end_date={$endDate}");

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertSame(5000, (int) $data['total_income']);
        $this->assertSame(1500, (int) $data['total_expense']);
        $this->assertSame(3500, (int) $data['balance']);
    }

    public function test_user_without_telegram_link_cannot_get_summary(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/transactions/summary');

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_daily_chart_returns_expected_dataset_shape(): void
    {
        [$memberUser, $memberTelegramUser] = $this->createUserWithTelegram(level: 2);

        Transaction::factory()->create([
            'user_id' => $memberTelegramUser->user_id,
            'type' => 'income',
            'amount' => 3000,
            'created_at' => now()->subDays(2),
        ]);
        Transaction::factory()->create([
            'user_id' => $memberTelegramUser->user_id,
            'type' => 'expense',
            'amount' => 1200,
            'created_at' => now()->subDays(2),
        ]);

        Sanctum::actingAs($memberUser);

        $response = $this->getJson('/api/transactions/daily-chart?start_date=' . now()->subDays(2)->format('Y-m-d') . '&end_date=' . now()->format('Y-m-d'));

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $labels = $response->json('data.labels');
        $datasets = $response->json('data.datasets');

        $this->assertCount(2, $datasets);
        $this->assertSame('Income', $datasets[0]['label']);
        $this->assertSame('Expense', $datasets[1]['label']);
        $this->assertCount(count($labels), $datasets[0]['data']);
        $this->assertCount(count($labels), $datasets[1]['data']);
    }

    public function test_daily_chart_rejects_overlong_range(): void
    {
        [$memberUser] = $this->createUserWithTelegram(level: 2);
        Sanctum::actingAs($memberUser);

        $response = $this->getJson('/api/transactions/daily-chart?start_date=2020-01-01&end_date=2022-01-05');

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_get_user_detail_with_commands(): void
    {
        [$adminUser] = $this->createUserWithTelegram(level: 1);
        $targetTelegramUser = TelegramUser::factory()->create();

        $command = TelegramUserCommand::factory()->create([
            'user_id' => $targetTelegramUser->user_id,
            'command' => '/help',
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->getJson('/api/users/' . $targetTelegramUser->user_id);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.user_id', $targetTelegramUser->user_id)
            ->assertJsonPath('data.commands.0.id', $command->id);
    }

    public function test_non_owner_non_admin_cannot_delete_transaction(): void
    {
        [$memberUser] = $this->createUserWithTelegram(level: 2);
        $otherTelegramUser = TelegramUser::factory()->create();

        $transaction = Transaction::factory()->create([
            'user_id' => $otherTelegramUser->user_id,
        ]);

        Sanctum::actingAs($memberUser);

        $response = $this->deleteJson('/api/transactions/' . $transaction->id);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_non_admin_bulk_delete_only_deletes_owned_transactions(): void
    {
        [$memberUser, $memberTelegramUser] = $this->createUserWithTelegram(level: 2);

        $owned = Transaction::factory()->create([
            'user_id' => $memberTelegramUser->user_id,
        ]);
        $otherTelegramUser = TelegramUser::factory()->create();
        $notOwned = Transaction::factory()->create([
            'user_id' => $otherTelegramUser->user_id,
        ]);

        Sanctum::actingAs($memberUser);

        $response = $this->postJson('/api/transactions/bulk-delete', [
            'ids' => [$owned->id, $notOwned->id],
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted', 1);

        $this->assertDatabaseMissing('transactions', ['id' => $owned->id]);
        $this->assertDatabaseHas('transactions', ['id' => $notOwned->id]);
    }

    private function createUserWithTelegram(int $level = 2): array
    {
        $telegramUser = TelegramUser::factory()->create(['level' => $level]);
        $user = User::factory()->create([
            'telegram_user_id' => $telegramUser->id,
        ]);

        return [$user, $telegramUser];
    }
}
