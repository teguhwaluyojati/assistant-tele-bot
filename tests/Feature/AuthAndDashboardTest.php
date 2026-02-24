<?php

namespace Tests\Feature;

use App\Models\TelegramUser;
use App\Models\TelegramUserCommand;
use App\Models\LoginHistory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_sets_auth_cookie(): void
    {
        $password = 'secret123';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response
            ->assertStatus(200)
            ->assertCookie('auth_token')
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_non_admin_cannot_update_user_role(): void
    {
        $telegramUser = TelegramUser::factory()->create(['level' => 2]);
        $user = User::factory()->create([
            'telegram_user_id' => $telegramUser->id,
        ]);

        $targetUser = TelegramUser::factory()->create(['level' => 2]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/users/{$targetUser->user_id}/role", [
            'level' => 1,
        ]);

        $response->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_admin_can_get_recent_commands(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        $command = TelegramUserCommand::factory()->create([
            'user_id' => $adminTelegramUser->user_id,
            'command' => '/start',
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->getJson('/api/dashboard/recent-commands');

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.command', $command->command);
    }

    public function test_admin_can_get_recent_logins(): void
    {
        Cache::flush();

        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        LoginHistory::factory()->create([
            'email' => 'older@example.com',
            'created_at' => now()->subHours(2),
        ]);
        LoginHistory::factory()->create([
            'email' => 'latest@example.com',
            'created_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->getJson('/api/dashboard/recent-logins');

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.email', 'latest@example.com');
    }

    public function test_non_admin_gets_only_own_recent_login(): void
    {
        Cache::flush();

        $telegramUser = TelegramUser::factory()->create(['level' => 2]);
        $user = User::factory()->create([
            'telegram_user_id' => $telegramUser->id,
            'email' => 'member@example.com',
        ]);

        LoginHistory::factory()->create([
            'email' => 'member@example.com',
            'created_at' => now()->subMinutes(10),
        ]);
        LoginHistory::factory()->create([
            'email' => 'other@example.com',
            'created_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/recent-logins');

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.email', 'member@example.com');
    }

    public function test_transactions_filter_and_sort_for_non_admin(): void
    {
        $telegramUser = TelegramUser::factory()->create(['level' => 2]);
        $user = User::factory()->create([
            'telegram_user_id' => $telegramUser->id,
        ]);

        Transaction::factory()->create([
            'user_id' => $telegramUser->user_id,
            'type' => 'income',
            'amount' => 1000,
        ]);
        Transaction::factory()->create([
            'user_id' => $telegramUser->user_id,
            'type' => 'income',
            'amount' => 5000,
        ]);
        Transaction::factory()->create([
            'user_id' => $telegramUser->user_id,
            'type' => 'expense',
            'amount' => 2000,
        ]);
        Transaction::factory()->create([
            'user_id' => 999999,
            'type' => 'income',
            'amount' => 50,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/transactions?type=income&sort=amount&direction=asc');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $data = $response->json('data.data');
        $this->assertCount(2, $data);
        $this->assertSame(1000, $data[0]['amount']);
        $this->assertSame(5000, $data[1]['amount']);
    }
}
