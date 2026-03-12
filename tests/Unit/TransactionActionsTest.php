<?php

namespace Tests\Unit;

use App\Actions\Transactions\BulkDeleteTransactionsAction;
use App\Actions\Transactions\CreateTransactionAction;
use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TransactionActionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_transaction_action_returns_error_when_user_not_linked(): void
    {
        $action = app(CreateTransactionAction::class);
        $user = User::factory()->create();

        $result = $action->execute($user, [
            'type' => 'expense',
            'amount' => 10000,
            'description' => 'makan siang',
            'category' => null,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame(403, $result['status']);
        $this->assertSame('User not linked to Telegram account.', $result['message']);
    }

    public function test_bulk_delete_action_deletes_only_authorized_member_transactions(): void
    {
        $action = app(BulkDeleteTransactionsAction::class);

        $memberTelegram = TelegramUser::factory()->create(['level' => 2]);
        $memberUser = User::factory()->create(['telegram_user_id' => $memberTelegram->id]);

        $otherTelegram = TelegramUser::factory()->create(['level' => 2]);

        $owned = Transaction::factory()->create(['user_id' => $memberTelegram->user_id]);
        $notOwned = Transaction::factory()->create(['user_id' => $otherTelegram->user_id]);

        $result = $action->execute($memberUser, [$owned->id, $notOwned->id]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['deleted']);

        $this->assertDatabaseMissing('transactions', ['id' => $owned->id]);
        $this->assertDatabaseHas('transactions', ['id' => $notOwned->id]);
    }
}
