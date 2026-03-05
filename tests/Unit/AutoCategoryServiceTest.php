<?php

namespace Tests\Unit;

use App\Services\AutoCategoryLlmService;
use App\Services\AutoCategoryService;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class AutoCategoryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('autocategory.ml.enabled', false);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_infer_returns_category_for_matching_expense_description(): void
    {
        $service = new AutoCategoryService();

        $result = $service->infer('Makan siang di warung', 'expense');

        $this->assertNotNull($result);
        $this->assertSame('Food & Drink', $result['category']);
        $this->assertGreaterThan(0, $result['confidence']);
    }

    public function test_infer_returns_null_for_empty_description(): void
    {
        $service = new AutoCategoryService();

        $this->assertNull($service->infer('', 'expense'));
        $this->assertNull($service->infer(null, 'income'));
    }

    public function test_infer_respects_transaction_type_dictionary(): void
    {
        $service = new AutoCategoryService();

        $result = $service->infer('gaji bulan ini', 'income');

        $this->assertNotNull($result);
        $this->assertSame('Salary', $result['category']);
    }

    public function test_infer_maps_nonton_film_to_entertainment(): void
    {
        $service = new AutoCategoryService();

        $result = $service->infer('nonton film weekend', 'expense');

        $this->assertNotNull($result);
        $this->assertSame('Entertainment', $result['category']);
    }

    public function test_infer_maps_slang_nntn_flm_to_entertainment(): void
    {
        $service = new AutoCategoryService();

        $result = $service->infer('nntn flm malem', 'expense');

        $this->assertNotNull($result);
        $this->assertSame('Entertainment', $result['category']);
    }

    public function test_infer_maps_slang_mkn_to_food_and_drink(): void
    {
        $service = new AutoCategoryService();

        $result = $service->infer('mkn siang kantor', 'expense');

        $this->assertNotNull($result);
        $this->assertSame('Food & Drink', $result['category']);
    }

    public function test_infer_returns_null_for_unmatched_text(): void
    {
        $service = new AutoCategoryService();

        $result = $service->infer('bayar sesuatu random tanpa konteks', 'expense');

        $this->assertNull($result);
    }

    public function test_infer_maps_topup_to_shopping(): void
    {
        $service = new AutoCategoryService();

        $result = $service->infer('top up saldo ewallet', 'expense');

        $this->assertNotNull($result);
        $this->assertSame('Shopping', $result['category']);
    }

    public function test_infer_maps_kontrakan_to_bills_and_utilities(): void
    {
        $service = new AutoCategoryService();

        $result = $service->infer('bayar kontrakan bulanan', 'expense');

        $this->assertNotNull($result);
        $this->assertSame('Bills & Utilities', $result['category']);
    }

    public function test_infer_uses_llm_fallback_when_rule_and_ml_do_not_match(): void
    {
        Config::set('autocategory.llm.enabled', true);
        Config::set('autocategory.llm.rollout_percentage', 100);
        Config::set('autocategory.llm.min_confidence', 0.7);

        $llmService = Mockery::mock(AutoCategoryLlmService::class);
        $llmService->shouldReceive('inferCategory')
            ->once()
            ->andReturn([
                'category' => 'Pet Care',
                'confidence' => 0.91,
            ]);

        $service = new AutoCategoryService($llmService);

        $result = $service->infer('vaksin booster anabul bulanan', 'expense');

        $this->assertNotNull($result);
        $this->assertSame('Pet Care', $result['category']);
        $this->assertSame(0.91, $result['confidence']);
    }

    public function test_infer_does_not_call_llm_when_rollout_is_zero_percent(): void
    {
        Config::set('autocategory.llm.enabled', true);
        Config::set('autocategory.llm.rollout_percentage', 0);

        $llmService = Mockery::mock(AutoCategoryLlmService::class);
        $llmService->shouldNotReceive('inferCategory');

        $service = new AutoCategoryService($llmService);

        $result = $service->infer('vaksin booster anabul bulanan', 'expense');

        $this->assertNull($result);
    }
}
