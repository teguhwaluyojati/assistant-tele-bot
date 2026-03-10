<?php

namespace Tests\Unit;

use App\Services\AutoCategoryService;
use App\Services\TransactionCategoryService;
use Mockery;
use Tests\TestCase;

class TransactionCategoryServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_resolve_uses_manual_category_when_provided(): void
    {
        $autoCategoryService = Mockery::mock(AutoCategoryService::class);
        $autoCategoryService->shouldNotReceive('infer');

        $service = new TransactionCategoryService($autoCategoryService);

        $result = $service->resolve('makan malam', 'Food & Drink', 'expense');

        $this->assertSame('makan malam', $result['description']);
        $this->assertSame('Food & Drink', $result['category']);
        $this->assertSame('manual', $result['category_source']);
        $this->assertSame(1.0, $result['category_confidence']);
    }

    public function test_resolve_uses_auto_category_when_manual_is_empty(): void
    {
        $autoCategoryService = Mockery::mock(AutoCategoryService::class);
        $autoCategoryService->shouldReceive('infer')
            ->once()
            ->with('nonton film', 'expense')
            ->andReturn([
                'category' => 'Entertainment',
                'confidence' => 0.87,
            ]);

        $service = new TransactionCategoryService($autoCategoryService);

        $result = $service->resolve('nonton film', ' ', 'expense');

        $this->assertSame('nonton film', $result['description']);
        $this->assertSame('Entertainment', $result['category']);
        $this->assertSame('auto', $result['category_source']);
        $this->assertSame(0.87, $result['category_confidence']);
    }

    public function test_resolve_returns_null_category_for_empty_description_and_manual(): void
    {
        $autoCategoryService = Mockery::mock(AutoCategoryService::class);
        $autoCategoryService->shouldNotReceive('infer');

        $service = new TransactionCategoryService($autoCategoryService);

        $result = $service->resolve('   ', '', 'income');

        $this->assertSame('', $result['description']);
        $this->assertNull($result['category']);
        $this->assertNull($result['category_source']);
        $this->assertNull($result['category_confidence']);
    }
}
