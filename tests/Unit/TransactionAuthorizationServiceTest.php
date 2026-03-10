<?php

namespace Tests\Unit;

use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionAuthorizationService;
use Tests\TestCase;

class TransactionAuthorizationServiceTest extends TestCase
{
    public function test_is_admin_returns_true_for_admin_linked_user(): void
    {
        $service = new TransactionAuthorizationService();

        $user = new User();
        $telegramUser = new TelegramUser([
            'user_id' => 1001,
            'level' => 1,
        ]);
        $user->setRelation('telegramUser', $telegramUser);

        $this->assertTrue($service->isAdmin($user));
    }

    public function test_linked_chat_id_returns_null_when_no_telegram_link(): void
    {
        $service = new TransactionAuthorizationService();

        $user = new User();
        $user->setRelation('telegramUser', null);

        $this->assertNull($service->linkedChatId($user));
    }

    public function test_can_manage_transaction_returns_true_for_owner(): void
    {
        $service = new TransactionAuthorizationService();

        $user = new User();
        $telegramUser = new TelegramUser([
            'user_id' => 2002,
            'level' => 2,
        ]);
        $user->setRelation('telegramUser', $telegramUser);

        $transaction = new Transaction([
            'user_id' => 2002,
            'type' => 'expense',
            'amount' => 10000,
        ]);

        $this->assertTrue($service->canManageTransaction($user, $transaction));
    }

    public function test_can_manage_transaction_returns_false_for_non_owner_member(): void
    {
        $service = new TransactionAuthorizationService();

        $user = new User();
        $telegramUser = new TelegramUser([
            'user_id' => 2002,
            'level' => 2,
        ]);
        $user->setRelation('telegramUser', $telegramUser);

        $transaction = new Transaction([
            'user_id' => 9999,
            'type' => 'expense',
            'amount' => 10000,
        ]);

        $this->assertFalse($service->canManageTransaction($user, $transaction));
    }
}
