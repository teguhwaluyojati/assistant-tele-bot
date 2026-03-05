<?php

namespace App\Services;

class AutoCategoryService
{
    private const DEFAULT_MIN_CONFIDENCE = 0.60;

    public function infer(?string $description, ?string $type = null): ?array
    {
        $normalized = $this->normalize($description);
        if ($normalized === '') {
            return null;
        }

        $typesToCheck = [];
        if (in_array($type, ['income', 'expense'], true)) {
            $typesToCheck[] = $type;
        } else {
            $typesToCheck = ['expense', 'income'];
        }

        $bestCategory = null;
        $bestScore = 0;

        foreach ($typesToCheck as $candidateType) {
            $categories = $this->categoryKeywords()[$candidateType] ?? [];
            $synonyms = $this->categorySynonyms()[$candidateType] ?? [];

            foreach ($categories as $category => $keywords) {
                $keywords = array_values(array_unique(array_merge($keywords, $synonyms[$category] ?? [])));
                $score = 0;
                foreach ($keywords as $keyword) {
                    $score += $this->keywordScore($normalized, $keyword);
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCategory = $category;
                }
            }
        }

        if ($bestScore <= 0 || $bestCategory === null) {
            return null;
        }

        $confidence = min(0.97, 0.46 + ($bestScore * 0.16));

        if ($confidence < $this->minConfidence()) {
            return null;
        }

        return [
            'category' => $bestCategory,
            'confidence' => round($confidence, 2),
        ];
    }

    private function keywordScore(string $normalizedDescription, string $keyword): float
    {
        $normalizedKeyword = $this->normalize($keyword);
        if ($normalizedKeyword === '') {
            return 0;
        }

        if (str_contains($normalizedKeyword, ' ')) {
            return str_contains($normalizedDescription, $normalizedKeyword) ? 1.2 : 0;
        }

        $pattern = '/\\b' . preg_quote($normalizedKeyword, '/') . '\\b/u';

        return preg_match($pattern, $normalizedDescription) === 1 ? 1.0 : 0;
    }

    private function normalize(?string $text): string
    {
        $normalized = strtolower(trim((string) $text));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? '';

        foreach ($this->normalizationMap() as $variant => $canonical) {
            $pattern = '/\\b' . preg_quote($variant, '/') . '\\b/u';
            $normalized = preg_replace($pattern, $canonical, $normalized) ?? $normalized;
        }

        $normalized = preg_replace('/(.)\\1{2,}/u', '$1$1', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return $normalized;
    }

    private function categoryKeywords(): array
    {
        $keywords = config('autocategory.category_keywords', []);

        return is_array($keywords) ? $keywords : [];
    }

    private function categorySynonyms(): array
    {
        $synonyms = config('autocategory.category_synonyms', []);

        return is_array($synonyms) ? $synonyms : [];
    }

    private function normalizationMap(): array
    {
        $map = config('autocategory.normalization_map', []);

        return is_array($map) ? $map : [];
    }

    private function minConfidence(): float
    {
        return (float) config('autocategory.min_confidence', self::DEFAULT_MIN_CONFIDENCE);
    }
}
