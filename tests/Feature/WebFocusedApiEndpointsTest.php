<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\TelegramUser;
use App\Models\TelegramUserCommand;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebFocusedApiEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_api_user_returns_authenticated_user_with_telegram_relation(): void
    {
        [$user, $telegramUser] = $this->createUserWithTelegram(level: 2);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response
            ->assertStatus(200)
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('telegram_user.id', $telegramUser->id);
    }

    public function test_admin_can_get_users_endpoint(): void
    {
        [$adminUser] = $this->createUserWithTelegram(level: 1);
        $targetTelegramUser = TelegramUser::factory()->create();
        User::factory()->create([
            'telegram_user_id' => $targetTelegramUser->id,
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->getJson('/api/users');

        $response
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'per_page'])
            ->assertJsonFragment(['user_id' => $targetTelegramUser->user_id]);
    }

    public function test_user_can_get_only_own_commands_from_me_endpoint(): void
    {
        [$user, $telegramUser] = $this->createUserWithTelegram(level: 2);
        $otherTelegramUser = TelegramUser::factory()->create();

        TelegramUserCommand::factory()->create([
            'user_id' => $telegramUser->user_id,
            'command' => '/start',
        ]);
        TelegramUserCommand::factory()->create([
            'user_id' => $telegramUser->user_id,
            'command' => '/help',
        ]);
        TelegramUserCommand::factory()->create([
            'user_id' => $otherTelegramUser->user_id,
            'command' => '/admin',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users/me/commands');

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $commands = $response->json('data');
        $this->assertCount(2, $commands);
        $this->assertTrue(collect($commands)->every(fn ($row) => $row['user_id'] === $telegramUser->user_id));
    }

    public function test_admin_can_get_history_login_endpoint(): void
    {
        [$adminUser] = $this->createUserWithTelegram(level: 1);
        $adminUser->forceFill(['email' => 'history-admin@example.com'])->save();

        LoginHistory::factory()->create([
            'email' => 'history-admin@example.com',
            'created_at' => '2099-03-01 09:00:00',
        ]);
        $latest = LoginHistory::factory()->create([
            'email' => 'history-admin@example.com',
            'created_at' => '2099-03-01 10:00:00',
        ]);
        LoginHistory::factory()->create([
            'email' => 'someone-else@example.com',
            'created_at' => '2099-03-01 11:00:00',
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->getJson('/api/history-login');

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $latest->id)
            ->assertJsonPath('data.email', 'history-admin@example.com');
    }

    public function test_transactions_export_returns_download_response(): void
    {
        [$user, $telegramUser] = $this->createUserWithTelegram(level: 2);

        Transaction::factory()->create([
            'user_id' => $telegramUser->user_id,
            'type' => 'income',
            'amount' => 120000,
            'description' => 'Salary',
            'created_at' => '2099-03-02 12:00:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->get('/api/transactions/export?start_date=2099-03-01&end_date=2099-03-31');

        $response->assertStatus(200);

        $contentDisposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment;', $contentDisposition);
        $this->assertStringContainsString('transactions-', $contentDisposition);
        $this->assertStringContainsString('.xlsx', $contentDisposition);
    }

    public function test_owner_can_update_transaction_endpoint(): void
    {
        [$user, $telegramUser] = $this->createUserWithTelegram(level: 2);

        $transaction = Transaction::factory()->create([
            'user_id' => $telegramUser->user_id,
            'type' => 'expense',
            'amount' => 5000,
            'description' => 'Old note',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/transactions/' . $transaction->id, [
            'type' => 'income',
            'amount' => 7500,
            'description' => 'Updated note',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.type', 'income')
            ->assertJsonPath('data.amount', 7500)
            ->assertJsonPath('data.description', 'Updated note');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'type' => 'income',
            'amount' => 7500,
            'description' => 'Updated note',
        ]);
    }

    public function test_admin_can_get_all_user_commands_endpoint(): void
    {
        [$adminUser, $adminTelegramUser] = $this->createUserWithTelegram(level: 1);
        $otherTelegramUser = TelegramUser::factory()->create();

        TelegramUserCommand::factory()->create([
            'user_id' => $adminTelegramUser->user_id,
            'command' => '/start',
            'created_at' => '2099-03-03 09:00:00',
        ]);
        TelegramUserCommand::factory()->create([
            'user_id' => $otherTelegramUser->user_id,
            'command' => '/help',
            'created_at' => '2099-03-03 10:00:00',
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->getJson('/api/users/commands?per_page=10');

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['data', 'current_page', 'per_page', 'total']]);

        $rows = $response->json('data.data');
        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertTrue(collect($rows)->contains(fn ($row) => ($row['command'] ?? null) === '/start'));
        $this->assertTrue(collect($rows)->contains(fn ($row) => ($row['command'] ?? null) === '/help'));
    }

    public function test_admin_can_export_all_user_commands_xlsx(): void
    {
        [$adminUser, $adminTelegramUser] = $this->createUserWithTelegram(level: 1);

        TelegramUserCommand::factory()->create([
            'user_id' => $adminTelegramUser->user_id,
            'command' => '/export-target',
            'created_at' => '2099-03-03 11:00:00',
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->get('/api/users/commands/export?search=export-target');

        $response->assertStatus(200);

        $contentDisposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment;', $contentDisposition);
        $this->assertStringContainsString('user-commands-', $contentDisposition);
        $this->assertStringContainsString('.xlsx', $contentDisposition);
    }

    public function test_user_can_submit_transaction_via_web_endpoint(): void
    {
        [$user, $telegramUser] = $this->createUserWithTelegram(level: 2);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions', [
            'type' => 'expense',
            'amount' => 12500,
            'transaction_date' => '2099-03-04T08:30',
            'description' => 'Lunch',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.amount', 12500)
            ->assertJsonPath('data.description', 'Lunch')
            ->assertJsonPath('data.user_id', $telegramUser->user_id);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $telegramUser->user_id,
            'type' => 'expense',
            'amount' => 12500,
            'description' => 'Lunch',
            'created_at' => '2099-03-04 08:30:00',
        ]);
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
