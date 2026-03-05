<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoCategoryLlmService
{
    public function inferCategory(string $description, ?string $type, array $allowedCategories): ?array
    {
        $description = trim($description);
        if ($description === '' || count($allowedCategories) === 0) {
            return null;
        }

        $apiKey = (string) config('autocategory.llm.api_key', '');
        $endpoint = (string) config('autocategory.llm.endpoint', 'https://api.openai.com/v1/chat/completions');
        $model = (string) config('autocategory.llm.model', 'gpt-4o-mini');
        $timeoutSeconds = max(2, (int) config('autocategory.llm.timeout_seconds', 8));
        $cacheTtlSeconds = max(30, (int) config('autocategory.llm.cache_ttl_seconds', 3600));

        if ($apiKey === '' || $endpoint === '' || $model === '') {
            return null;
        }

        $cacheKey = 'autocategory.llm.response.' . sha1(strtolower($description) . '|' . ($type ?? 'all') . '|' . implode('|', $allowedCategories));

        return Cache::remember($cacheKey, $cacheTtlSeconds, function () use (
            $description,
            $type,
            $allowedCategories,
            $endpoint,
            $apiKey,
            $model,
            $timeoutSeconds
        ) {
            $systemPrompt = 'You are a strict finance category classifier. Return JSON only with fields: category, confidence, reason. '
                . 'Category MUST be one item from allowed_categories. confidence must be number between 0 and 1.';

            $userPayload = [
                'transaction_type' => $type,
                'description' => $description,
                'allowed_categories' => array_values($allowedCategories),
            ];

            try {
                $response = Http::withToken($apiKey)
                    ->acceptJson()
                    ->timeout($timeoutSeconds)
                    ->post($endpoint, [
                        'model' => $model,
                        'temperature' => 0,
                        'response_format' => ['type' => 'json_object'],
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => json_encode($userPayload)],
                        ],
                    ]);

                if (!$response->successful()) {
                    Log::warning('autocategory.llm.http_failure', [
                        'status' => $response->status(),
                    ]);

                    return null;
                }

                $json = $response->json();
                $content = (string) data_get($json, 'choices.0.message.content', '');
                if ($content === '') {
                    return null;
                }

                $parsed = json_decode($content, true);
                if (!is_array($parsed)) {
                    return null;
                }

                $category = trim((string) ($parsed['category'] ?? ''));
                $confidence = (float) ($parsed['confidence'] ?? 0);

                if ($category === '' || !in_array($category, $allowedCategories, true)) {
                    return null;
                }

                if ($confidence <= 0 || $confidence > 1) {
                    return null;
                }

                return [
                    'category' => $category,
                    'confidence' => $confidence,
                ];
            } catch (\Throwable $e) {
                Log::warning('autocategory.llm.exception', [
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }
}
