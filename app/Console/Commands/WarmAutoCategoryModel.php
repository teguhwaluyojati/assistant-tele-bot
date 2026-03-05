<?php

namespace App\Console\Commands;

use App\Services\AutoCategoryService;
use Illuminate\Console\Command;

class WarmAutoCategoryModel extends Command
{
    protected $signature = 'app:autocategory:warm
                            {--rebuild : Rebuild model cache before warming}
                            {--type=* : Model type to warm (all, expense, income)}';

    protected $description = 'Warm/rebuild auto-category ML fallback model cache.';

    public function __construct(private readonly AutoCategoryService $autoCategoryService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $types = $this->option('type');
        $types = is_array($types) && count($types) > 0 ? $types : ['all', 'expense', 'income'];

        $invalid = collect($types)
            ->map(fn ($type) => strtolower(trim((string) $type)))
            ->filter(fn ($type) => !in_array($type, ['all', 'expense', 'income'], true))
            ->unique()
            ->values()
            ->all();

        if (count($invalid) > 0) {
            $this->error('Invalid --type value(s): ' . implode(', ', $invalid));
            $this->line('Allowed values: all, expense, income');

            return self::FAILURE;
        }

        $rebuild = (bool) $this->option('rebuild');

        $this->info('Warming auto-category ML model cache...');

        $result = $this->autoCategoryService->warmMlModels($types, $rebuild);

        $rows = [];
        foreach ($result as $type => $stats) {
            $rows[] = [
                $type,
                $stats['trained'] ? 'yes' : 'no',
                $stats['total_docs'],
                $stats['categories'],
                $stats['vocabulary_size'],
                $stats['cache_key'],
            ];
        }

        $this->table(
            ['Type', 'Trained', 'Docs', 'Categories', 'Vocabulary', 'Cache Key'],
            $rows
        );

        $trainedCount = collect($result)->filter(fn ($stats) => $stats['trained'])->count();

        if ($trainedCount === 0) {
            $this->warn('No model trained. Check min_samples / allowed_sources / labeled data availability.');
        } else {
            $this->info("Warm complete. Trained model(s): {$trainedCount}");
        }

        return self::SUCCESS;
    }
}
