<?php

namespace Tests\Unit;

use App\Services\AutoCategoryService;
use Tests\TestCase;

class AutoCategoryServiceTest extends TestCase
{
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
}
