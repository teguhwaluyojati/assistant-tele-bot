<?php

namespace Tests\Unit;

use App\Models\TelegramUser;
use App\Models\Transaction;
use App\Services\TransactionPersistenceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TransactionPersistenceServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_save_new_persists_transaction(): void
    {
        $telegramUser = TelegramUser::factory()->create([
            'user_id' => 7777,
        ]);

        $transaction = new Transaction([
            'user_id' => $telegramUser->user_id,
            'type' => 'expense',
            'amount' => 12000,
            'description' => 'test persist',
            'category' => 'Food & Drink',
            'category_source' => 'manual',
            'category_confidence' => 1,
        ]);

        $service = new TransactionPersistenceService();
        $service->saveNew($transaction);

        $this->assertNotNull($transaction->id);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'user_id' => 7777,
            'type' => 'expense',
            'amount' => 12000,
        ]);
    }
}
