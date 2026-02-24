<?php

namespace Tests\Feature;

use App\Models\TelegramUser;
use App\Models\TelegramUserCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
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
}
