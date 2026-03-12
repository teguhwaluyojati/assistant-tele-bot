<?php

namespace Tests\Unit;

use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionAuthorizationService;
use App\Services\TransactionBulkDeleteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TransactionBulkDeleteServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_resolve_authorized_transactions_returns_missing_telegram_for_member_without_link(): void
    {
        $authorizationService = new TransactionAuthorizationService();
        $service = new TransactionBulkDeleteService($authorizationService);

        $user = User::factory()->create();

        $result = $service->resolveAuthorizedTransactions($user, [1, 2]);

        $this->assertSame('missing_telegram', $result['status']);
        $this->assertCount(0, $result['transactions']);
    }

    public function test_resolve_authorized_transactions_filters_non_admin_to_owned_transactions(): void
    {
        $authorizationService = new TransactionAuthorizationService();
        $service = new TransactionBulkDeleteService($authorizationService);

        $memberTelegram = TelegramUser::factory()->create(['level' => 2]);
        $memberUser = User::factory()->create(['telegram_user_id' => $memberTelegram->id]);

        $otherTelegram = TelegramUser::factory()->create(['level' => 2]);

        $owned = Transaction::factory()->create(['user_id' => $memberTelegram->user_id]);
        $notOwned = Transaction::factory()->create(['user_id' => $otherTelegram->user_id]);

        $result = $service->resolveAuthorizedTransactions($memberUser, [$owned->id, $notOwned->id]);

        $this->assertSame('ok', $result['status']);
        $this->assertCount(1, $result['transactions']);
        $this->assertSame($owned->id, $result['transactions']->first()->id);
    }

    public function test_resolve_authorized_transactions_returns_all_for_admin(): void
    {
        $authorizationService = new TransactionAuthorizationService();
        $service = new TransactionBulkDeleteService($authorizationService);

        $adminTelegram = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create(['telegram_user_id' => $adminTelegram->id]);

        $otherTelegram = TelegramUser::factory()->create(['level' => 2]);

        $first = Transaction::factory()->create(['user_id' => $adminTelegram->user_id]);
        $second = Transaction::factory()->create(['user_id' => $otherTelegram->user_id]);

        $result = $service->resolveAuthorizedTransactions($adminUser, [$first->id, $second->id]);

        $this->assertSame('ok', $result['status']);
        $this->assertCount(2, $result['transactions']);
    }
}
