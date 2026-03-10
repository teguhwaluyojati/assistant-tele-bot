<?php

namespace App\Services;

class TransactionCategoryService
{
    public function __construct(private AutoCategoryService $autoCategoryService)
    {
    }

    public function resolve(?string $description, ?string $manualCategory, string $type): array
    {
        $normalizedDescription = trim((string) $description);
        $normalizedManualCategory = trim((string) $manualCategory);

        $category = null;
        $categorySource = null;
        $categoryConfidence = null;

        if ($normalizedManualCategory !== '') {
            $category = $normalizedManualCategory;
            $categorySource = 'manual';
            $categoryConfidence = 1.00;
        } elseif ($normalizedDescription !== '') {
            $inferredCategory = $this->autoCategoryService->infer($normalizedDescription, $type);
            if ($inferredCategory) {
                $category = $inferredCategory['category'];
                $categorySource = 'auto';
                $categoryConfidence = $inferredCategory['confidence'];
            }
        }

        return [
            'description' => $normalizedDescription,
            'category' => $category,
            'category_source' => $categorySource,
            'category_confidence' => $categoryConfidence,
        ];
    }
}
