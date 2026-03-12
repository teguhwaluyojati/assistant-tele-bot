<?php

namespace Tests\Unit;

use App\Models\TelegramUser;
use App\Models\User;
use App\Services\TransactionAccessGuardService;
use App\Services\TransactionAuthorizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TransactionAccessGuardServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ensure_list_access_returns_error_when_user_not_linked(): void
    {
        $service = new TransactionAccessGuardService(new TransactionAuthorizationService());
        $user = User::factory()->create();

        $result = $service->ensureListAccess($user);

        $this->assertFalse($result['ok']);
        $this->assertSame('User not linked to Telegram account.', $result['error']['message']);
        $this->assertSame(403, $result['error']['status']);
    }

    public function test_ensure_summary_access_returns_chat_id_for_linked_user(): void
    {
        $service = new TransactionAccessGuardService(new TransactionAuthorizationService());

        $telegramUser = TelegramUser::factory()->create();
        $user = User::factory()->create([
            'telegram_user_id' => $telegramUser->id,
        ]);

        $result = $service->ensureSummaryAccess($user);

        $this->assertTrue($result['ok']);
        $this->assertSame((int) $telegramUser->user_id, $result['chat_id']);
    }

    public function test_resolve_daily_chart_scope_returns_null_chat_id_for_admin(): void
    {
        $service = new TransactionAccessGuardService(new TransactionAuthorizationService());

        $adminTelegram = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegram->id,
        ]);

        $result = $service->resolveDailyChartScope($adminUser);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['chat_id']);
    }

    public function test_ensure_export_access_returns_error_for_non_admin_without_link(): void
    {
        $service = new TransactionAccessGuardService(new TransactionAuthorizationService());
        $user = User::factory()->create();

        $result = $service->ensureExportAccess($user, false);

        $this->assertFalse($result['ok']);
        $this->assertSame('User not linked to Telegram account.', $result['error']['message']);
        $this->assertSame(403, $result['error']['status']);
    }
}
