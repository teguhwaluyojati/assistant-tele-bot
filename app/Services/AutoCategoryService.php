<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

class AutoCategoryService
{
    private const DEFAULT_MIN_CONFIDENCE = 0.60;

    public function warmMlModels(array $types = ['all', 'expense', 'income'], bool $rebuild = false): array
    {
        $normalizedTypes = collect($types)
            ->map(fn ($type) => strtolower(trim((string) $type)))
            ->filter(fn ($type) => in_array($type, ['all', 'expense', 'income'], true))
            ->unique()
            ->values()
            ->all();

        if (count($normalizedTypes) === 0) {
            $normalizedTypes = ['all', 'expense', 'income'];
        }

        $result = [];

        foreach ($normalizedTypes as $type) {
            $typeForModel = $type === 'all' ? null : $type;
            $cacheKey = $this->mlCacheKey($typeForModel);

            if ($rebuild) {
                Cache::forget($cacheKey);
            }

            $model = $this->mlModel($typeForModel);

            $result[$type] = [
                'cache_key' => $cacheKey,
                'trained' => $model !== null,
                'total_docs' => (int) ($model['total_docs'] ?? 0),
                'categories' => count($model['category_doc_count'] ?? []),
                'vocabulary_size' => (int) ($model['vocabulary_size'] ?? 0),
            ];
        }

        return $result;
    }

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
            return $this->mlFallbackInfer($normalized, $type);
        }

        $confidence = min(0.97, 0.46 + ($bestScore * 0.16));

        if ($confidence < $this->minConfidence()) {
            return $this->mlFallbackInfer($normalized, $type);
        }

        return [
            'category' => $bestCategory,
            'confidence' => round($confidence, 2),
        ];
    }

    private function mlFallbackInfer(string $normalizedDescription, ?string $type = null): ?array
    {
        if (!$this->mlEnabled()) {
            return null;
        }

        $model = $this->mlModel($type);
        if (!$model) {
            return null;
        }

        $tokens = $this->tokenize($normalizedDescription);
        if (count($tokens) === 0) {
            return null;
        }

        $categoryDocCount = $model['category_doc_count'] ?? [];
        $categoryTokenCount = $model['category_token_count'] ?? [];
        $tokenCategoryCount = $model['token_category_count'] ?? [];
        $vocabularySize = max(1, (int) ($model['vocabulary_size'] ?? 1));
        $totalDocs = max(1, (int) ($model['total_docs'] ?? 1));

        if (count($categoryDocCount) === 0) {
            return null;
        }

        $scores = [];

        foreach ($categoryDocCount as $category => $docCount) {
            $prior = ($docCount + 1) / ($totalDocs + count($categoryDocCount));
            $logProb = log($prior);
            $totalTokensInCategory = (int) ($categoryTokenCount[$category] ?? 0);

            foreach ($tokens as $token) {
                $tokenCount = (int) ($tokenCategoryCount[$category][$token] ?? 0);
                $likelihood = ($tokenCount + 1) / ($totalTokensInCategory + $vocabularySize);
                $logProb += log($likelihood);
            }

            $scores[$category] = $logProb;
        }

        arsort($scores);
        $categories = array_keys($scores);

        if (count($categories) === 0) {
            return null;
        }

        $topCategory = $categories[0];
        $topScore = $scores[$topCategory];
        $secondScore = $scores[$categories[1]] ?? ($topScore - 10);

        $confidence = 1 / (1 + exp(-($topScore - $secondScore)));
        $margin = $topScore - $secondScore;

        if ($confidence < $this->mlMinConfidence() || $margin < $this->mlMinMargin()) {
            return null;
        }

        return [
            'category' => $topCategory,
            'confidence' => round(min(0.95, max(0.60, $confidence)), 2),
        ];
    }

    private function mlModel(?string $type = null): ?array
    {
        $cacheTtl = $this->mlCacheTtlSeconds();
        $cacheKey = $this->mlCacheKey($type);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($type) {
            return $this->buildMlModel($type);
        });
    }

    private function mlCacheKey(?string $type = null): string
    {
        return 'autocategory.ml_model.' . ($type ?: 'all');
    }

    private function buildMlModel(?string $type = null): ?array
    {
        $sources = $this->mlAllowedSources();
        $minSamples = $this->mlMinSamples();

        $query = Transaction::query()
            ->select(['description', 'category', 'type', 'category_source'])
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->whereNotNull('description')
            ->where('description', '!=', '');

        if (in_array($type, ['income', 'expense'], true)) {
            $query->where('type', $type);
        }

        if (count($sources) > 0) {
            $query->whereIn('category_source', $sources);
        }

        $rows = $query->get();

        if ($rows->count() < $minSamples) {
            return null;
        }

        $categoryDocCount = [];
        $categoryTokenCount = [];
        $tokenCategoryCount = [];
        $vocabulary = [];

        foreach ($rows as $row) {
            $category = trim((string) $row->category);
            if ($category === '') {
                continue;
            }

            $normalized = $this->normalize((string) $row->description);
            $tokens = $this->tokenize($normalized);
            if (count($tokens) === 0) {
                continue;
            }

            $categoryDocCount[$category] = ($categoryDocCount[$category] ?? 0) + 1;

            foreach ($tokens as $token) {
                $vocabulary[$token] = true;
                $categoryTokenCount[$category] = ($categoryTokenCount[$category] ?? 0) + 1;
                $tokenCategoryCount[$category][$token] = ($tokenCategoryCount[$category][$token] ?? 0) + 1;
            }
        }

        if (count($categoryDocCount) < 2) {
            return null;
        }

        return [
            'total_docs' => array_sum($categoryDocCount),
            'category_doc_count' => $categoryDocCount,
            'category_token_count' => $categoryTokenCount,
            'token_category_count' => $tokenCategoryCount,
            'vocabulary_size' => count($vocabulary),
        ];
    }

    private function tokenize(string $normalizedDescription): array
    {
        $stopwords = array_fill_keys($this->mlStopwords(), true);

        return collect(explode(' ', $normalizedDescription))
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => $token !== '' && mb_strlen($token) >= 3)
            ->filter(fn ($token) => !isset($stopwords[$token]))
            ->values()
            ->all();
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

    private function mlEnabled(): bool
    {
        return (bool) config('autocategory.ml.enabled', true);
    }

    private function mlCacheTtlSeconds(): int
    {
        return max(30, (int) config('autocategory.ml.cache_ttl_seconds', 600));
    }

    private function mlMinSamples(): int
    {
        return max(2, (int) config('autocategory.ml.min_samples', 10));
    }

    private function mlMinConfidence(): float
    {
        return (float) config('autocategory.ml.min_confidence', 0.65);
    }

    private function mlMinMargin(): float
    {
        return (float) config('autocategory.ml.min_margin', 0.25);
    }

    private function mlAllowedSources(): array
    {
        $sources = config('autocategory.ml.allowed_sources', ['manual']);

        return is_array($sources) ? $sources : [];
    }

    private function mlStopwords(): array
    {
        $stopwords = config('autocategory.ml.stopwords', []);

        return is_array($stopwords) ? $stopwords : [];
    }
}
