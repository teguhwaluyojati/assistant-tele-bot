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

    public function test_admin_cannot_create_user_for_non_member_telegram_account(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        $targetTelegramUser = TelegramUser::factory()->admin()->create();

        Sanctum::actingAs($adminUser);

        $response = $this->postJson('/api/users', [
            'name' => 'Linked Admin',
            'email' => 'linked-admin@example.com',
            'telegram_user_id' => $targetTelegramUser->user_id,
            'telegram_username' => $targetTelegramUser->username,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Admin can only create web users for member Telegram accounts.');

        $this->assertDatabaseMissing('users', [
            'email' => 'linked-admin@example.com',
        ]);
    }

    public function test_admin_create_user_rejects_telegram_username_conflict_with_other_telegram_id(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        $ownerTelegramUser = TelegramUser::factory()->create([
            'user_id' => 700001,
            'username' => 'sharedname',
            'level' => 2,
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->postJson('/api/users', [
            'name' => 'Conflict Username',
            'email' => 'conflict-username@example.com',
            'telegram_user_id' => 700002,
            'telegram_username' => 'sharedname',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Telegram username is already used by another Telegram ID.');

        $this->assertDatabaseHas('telegram_users', [
            'id' => $ownerTelegramUser->id,
            'user_id' => 700001,
            'username' => 'sharedname',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'conflict-username@example.com',
        ]);
    }

    public function test_admin_create_user_rejects_already_linked_telegram_account(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        $targetTelegramUser = TelegramUser::factory()->create(['level' => 2]);
        User::factory()->create([
            'telegram_user_id' => $targetTelegramUser->id,
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->postJson('/api/users', [
            'name' => 'Already Linked',
            'email' => 'already-linked@example.com',
            'telegram_user_id' => $targetTelegramUser->user_id,
            'telegram_username' => $targetTelegramUser->username,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This Telegram account is already linked to another web account.');

        $this->assertDatabaseMissing('users', [
            'email' => 'already-linked@example.com',
        ]);
    }

    public function test_admin_create_user_validation_errors_are_returned(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->postJson('/api/users', [
            'name' => '',
            'email' => 'not-an-email',
            'telegram_user_id' => 0,
            'telegram_username' => '',
            'password' => '123',
            'password_confirmation' => '321',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors([
                'name',
                'email',
                'telegram_user_id',
                'telegram_username',
                'password',
            ]);
    }

    public function test_admin_cannot_promote_member_to_superadmin_when_superadmin_exists(): void
    {
        TelegramUser::factory()->create(['level' => 0]);

        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);
        $targetTelegramUser = TelegramUser::factory()->create(['level' => 2]);

        Sanctum::actingAs($adminUser);

        $response = $this->putJson("/api/users/{$targetTelegramUser->user_id}/role", [
            'level' => 0,
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only superadmin can manage superadmin role.');

        $this->assertDatabaseHas('telegram_users', [
            'id' => $targetTelegramUser->id,
            'level' => 2,
        ]);
    }

    public function test_admin_can_bootstrap_first_superadmin_for_configured_id(): void
    {
        TelegramUser::query()->where('level', 0)->update(['level' => 1]);

        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);
        $targetTelegramUser = TelegramUser::factory()->create(['level' => 2]);

        $original = $this->setTelegramAdminIds((string) $targetTelegramUser->user_id);

        try {
            Sanctum::actingAs($adminUser);

            $response = $this->putJson("/api/users/{$targetTelegramUser->user_id}/role", [
                'level' => 0,
            ]);

            $response
                ->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.level', 0);

            $this->assertDatabaseHas('telegram_users', [
                'id' => $targetTelegramUser->id,
                'level' => 0,
            ]);
        } finally {
            $this->setTelegramAdminIds($original);
        }
    }

    public function test_admin_cannot_bootstrap_first_superadmin_for_non_configured_id(): void
    {
        TelegramUser::query()->where('level', 0)->update(['level' => 1]);

        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);
        $targetTelegramUser = TelegramUser::factory()->create(['level' => 2]);

        $original = $this->setTelegramAdminIds((string) ($targetTelegramUser->user_id + 99999));

        try {
            Sanctum::actingAs($adminUser);

            $response = $this->putJson("/api/users/{$targetTelegramUser->user_id}/role", [
                'level' => 0,
            ]);

            $response
                ->assertStatus(403)
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', 'Only superadmin can manage superadmin role.');

            $this->assertDatabaseHas('telegram_users', [
                'id' => $targetTelegramUser->id,
                'level' => 2,
            ]);
        } finally {
            $this->setTelegramAdminIds($original);
        }
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->putJson("/api/users/{$adminTelegramUser->user_id}/role", [
            'level' => 2,
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You cannot change your own role.');

        $this->assertDatabaseHas('telegram_users', [
            'id' => $adminTelegramUser->id,
            'level' => 1,
        ]);
    }

    public function test_superadmin_cannot_change_own_role(): void
    {
        $superAdminTelegramUser = TelegramUser::factory()->create(['level' => 0]);
        $superAdminUser = User::factory()->create([
            'telegram_user_id' => $superAdminTelegramUser->id,
        ]);

        Sanctum::actingAs($superAdminUser);

        $response = $this->putJson("/api/users/{$superAdminTelegramUser->user_id}/role", [
            'level' => 1,
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You cannot change your own role.');

        $this->assertDatabaseHas('telegram_users', [
            'id' => $superAdminTelegramUser->id,
            'level' => 0,
        ]);
    }

    public function test_superadmin_can_manage_admin_role_change(): void
    {
        $superAdminTelegramUser = TelegramUser::factory()->create(['level' => 0]);
        $superAdminUser = User::factory()->create([
            'telegram_user_id' => $superAdminTelegramUser->id,
        ]);
        $targetAdminTelegramUser = TelegramUser::factory()->admin()->create();

        Sanctum::actingAs($superAdminUser);

        $response = $this->putJson("/api/users/{$targetAdminTelegramUser->user_id}/role", [
            'level' => 2,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.level', 2);

        $this->assertDatabaseHas('telegram_users', [
            'id' => $targetAdminTelegramUser->id,
            'level' => 2,
        ]);
    }

    public function test_admin_cannot_delete_superadmin_account(): void
    {
        $superAdminTelegramUser = TelegramUser::factory()->create(['level' => 0]);
        User::factory()->create([
            'telegram_user_id' => $superAdminTelegramUser->id,
        ]);

        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->deleteJson("/api/users/{$superAdminTelegramUser->user_id}");

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Admin can only manage member accounts.');

        $this->assertDatabaseHas('telegram_users', [
            'id' => $superAdminTelegramUser->id,
        ]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->deleteJson("/api/users/{$adminTelegramUser->user_id}");

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You cannot delete your own account.');

        $this->assertDatabaseHas('telegram_users', [
            'id' => $adminTelegramUser->id,
        ]);
    }

    public function test_superadmin_cannot_delete_own_account(): void
    {
        $superAdminTelegramUser = TelegramUser::factory()->create(['level' => 0]);
        $superAdminUser = User::factory()->create([
            'telegram_user_id' => $superAdminTelegramUser->id,
        ]);

        Sanctum::actingAs($superAdminUser);

        $response = $this->deleteJson("/api/users/{$superAdminTelegramUser->user_id}");

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You cannot delete your own account.');

        $this->assertDatabaseHas('telegram_users', [
            'id' => $superAdminTelegramUser->id,
            'level' => 0,
        ]);
    }

    public function test_superadmin_can_delete_admin_account(): void
    {
        $superAdminTelegramUser = TelegramUser::factory()->create(['level' => 0]);
        $superAdminUser = User::factory()->create([
            'telegram_user_id' => $superAdminTelegramUser->id,
        ]);

        $targetAdminTelegramUser = TelegramUser::factory()->admin()->create();
        User::factory()->create([
            'telegram_user_id' => $targetAdminTelegramUser->id,
        ]);

        Sanctum::actingAs($superAdminUser);

        $response = $this->deleteJson("/api/users/{$targetAdminTelegramUser->user_id}");

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $targetAdminTelegramUser->user_id);

        $this->assertDatabaseMissing('telegram_users', [
            'id' => $targetAdminTelegramUser->id,
        ]);
    }

    public function test_superadmin_can_delete_another_superadmin_when_multiple_exist(): void
    {
        $actorSuperAdminTelegramUser = TelegramUser::factory()->create(['level' => 0]);
        $actorSuperAdminUser = User::factory()->create([
            'telegram_user_id' => $actorSuperAdminTelegramUser->id,
        ]);

        $targetSuperAdminTelegramUser = TelegramUser::factory()->create(['level' => 0]);
        User::factory()->create([
            'telegram_user_id' => $targetSuperAdminTelegramUser->id,
        ]);

        Sanctum::actingAs($actorSuperAdminUser);

        $response = $this->deleteJson("/api/users/{$targetSuperAdminTelegramUser->user_id}");

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $targetSuperAdminTelegramUser->user_id);

        $this->assertDatabaseMissing('telegram_users', [
            'id' => $targetSuperAdminTelegramUser->id,
        ]);
        $this->assertDatabaseHas('telegram_users', [
            'id' => $actorSuperAdminTelegramUser->id,
            'level' => 0,
        ]);
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
            ->assertJsonFragment([
                'id' => $command->id,
                'command' => $command->command,
            ]);
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
            'created_at' => '2099-02-01 08:00:00',
        ]);
        LoginHistory::factory()->create([
            'email' => 'latest@example.com',
            'created_at' => '2099-02-01 09:00:00',
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
        $otherTelegramUser = TelegramUser::factory()->create([
            'user_id' => 999999,
        ]);
        Transaction::factory()->create([
            'user_id' => $otherTelegramUser->user_id,
            'type' => 'income',
            'amount' => 50,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/transactions?type=income&sort=amount&direction=asc');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $data = $response->json('data.data');
        $this->assertCount(2, $data);

        $amounts = array_column($data, 'amount');
        sort($amounts);

        $this->assertSame([1000, 5000], $amounts);
    }

    private function setTelegramAdminIds(?string $value): ?string
    {
        $original = getenv('TELEGRAM_ADMIN_ID');

        if ($value === null || $value === '') {
            putenv('TELEGRAM_ADMIN_ID');
            unset($_ENV['TELEGRAM_ADMIN_ID'], $_SERVER['TELEGRAM_ADMIN_ID']);

            return $original === false ? null : $original;
        }

        putenv("TELEGRAM_ADMIN_ID={$value}");
        $_ENV['TELEGRAM_ADMIN_ID'] = $value;
        $_SERVER['TELEGRAM_ADMIN_ID'] = $value;

        return $original === false ? null : $original;
    }
}
