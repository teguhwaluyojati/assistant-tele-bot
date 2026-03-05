<?php

namespace Tests\Feature;

use App\Models\TelegramUser;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WarmAutoCategoryModelCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_command_warms_expense_model_cache_from_manual_labeled_history(): void
    {
        Config::set('autocategory.ml.enabled', true);
        Config::set('autocategory.ml.min_samples', 4);
        Config::set('autocategory.ml.allowed_sources', ['manual']);

        $telegramUser = TelegramUser::factory()->create();

        $samples = [
            ['description' => 'grooming kucing', 'category' => 'Pet Care'],
            ['description' => 'pasir kucing premium', 'category' => 'Pet Care'],
            ['description' => 'servis sepeda gunung', 'category' => 'Cycling'],
            ['description' => 'ban sepeda baru', 'category' => 'Cycling'],
        ];

        foreach ($samples as $sample) {
            Transaction::factory()->create([
                'user_id' => $telegramUser->user_id,
                'type' => 'expense',
                'amount' => 10000,
                'description' => $sample['description'],
                'category' => $sample['category'],
                'category_source' => 'manual',
                'category_confidence' => 1,
            ]);
        }

        Cache::forget('autocategory.ml_model.expense');

        $this->artisan('app:autocategory:warm', [
            '--type' => ['expense'],
            '--rebuild' => true,
        ])
            ->expectsOutput('Warming auto-category ML model cache...')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has('autocategory.ml_model.expense'));
    }

    public function test_command_fails_on_invalid_type_option(): void
    {
        $this->artisan('app:autocategory:warm', [
            '--type' => ['invalid-type'],
        ])
            ->expectsOutput('Invalid --type value(s): invalid-type')
            ->assertExitCode(1);
    }
}
