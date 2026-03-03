<?php

namespace Tests\Feature;

use App\Models\TelegramUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BootstrapSuperadminCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_command_fails_when_telegram_admin_id_env_is_empty(): void
    {
        TelegramUser::query()->where('level', 0)->update(['level' => 1]);
        $original = $this->setTelegramAdminIds(null);

        try {
            $this->artisan('app:bootstrap-superadmin')
                ->expectsOutput('TELEGRAM_ADMIN_ID is empty. Set it first (single ID or comma-separated IDs).')
                ->assertExitCode(1);
        } finally {
            $this->setTelegramAdminIds($original);
        }
    }

    public function test_command_fails_when_superadmin_already_exists(): void
    {
        TelegramUser::factory()->create(['level' => 0]);

        $original = $this->setTelegramAdminIds('123456');

        try {
            $this->artisan('app:bootstrap-superadmin')
                ->expectsOutput('A superadmin already exists. Bootstrap is blocked to prevent accidental privilege escalation.')
                ->assertExitCode(1);
        } finally {
            $this->setTelegramAdminIds($original);
        }
    }

    public function test_command_fails_when_chat_id_argument_not_in_configured_admin_ids(): void
    {
        TelegramUser::query()->where('level', 0)->update(['level' => 1]);
        $original = $this->setTelegramAdminIds('111111,222222');

        try {
            $this->artisan('app:bootstrap-superadmin', ['chat_id' => '333333'])
                ->expectsOutput('Chat ID 333333 is not listed in TELEGRAM_ADMIN_ID.')
                ->assertExitCode(1);
        } finally {
            $this->setTelegramAdminIds($original);
        }
    }

    public function test_command_fails_when_no_telegram_user_found_for_target_chat_ids(): void
    {
        TelegramUser::query()->where('level', 0)->update(['level' => 1]);
        $original = $this->setTelegramAdminIds('444444');

        try {
            $this->artisan('app:bootstrap-superadmin')
                ->expectsOutput('No Telegram user found for configured/admin-specified chat ID. Open the bot and send /start first.')
                ->assertExitCode(1);
        } finally {
            $this->setTelegramAdminIds($original);
        }
    }

    public function test_command_promotes_target_user_with_explicit_chat_id(): void
    {
        TelegramUser::query()->where('level', 0)->update(['level' => 1]);

        $target = TelegramUser::factory()->create([
            'user_id' => 777001,
            'level' => 2,
        ]);

        $original = $this->setTelegramAdminIds('777001,777002');

        try {
            $this->artisan('app:bootstrap-superadmin', ['chat_id' => '777001'])
                ->expectsOutput('Superadmin bootstrapped successfully for chat ID 777001.')
                ->assertExitCode(0);

            $this->assertDatabaseHas('telegram_users', [
                'id' => $target->id,
                'user_id' => 777001,
                'level' => 0,
            ]);
        } finally {
            $this->setTelegramAdminIds($original);
        }
    }

    public function test_command_promotes_first_available_user_from_configured_admin_ids_without_argument(): void
    {
        TelegramUser::query()->where('level', 0)->update(['level' => 1]);

        TelegramUser::factory()->create([
            'user_id' => 888001,
            'level' => 1,
        ]);

        $memberTarget = TelegramUser::factory()->create([
            'user_id' => 888002,
            'level' => 2,
        ]);

        $original = $this->setTelegramAdminIds('888001,888002');

        try {
            $this->artisan('app:bootstrap-superadmin')
                ->expectsOutput('Superadmin bootstrapped successfully for chat ID 888002.')
                ->assertExitCode(0);

            $this->assertDatabaseHas('telegram_users', [
                'id' => $memberTarget->id,
                'user_id' => 888002,
                'level' => 0,
            ]);
        } finally {
            $this->setTelegramAdminIds($original);
        }
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
