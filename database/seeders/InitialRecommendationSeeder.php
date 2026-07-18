<?php

namespace Database\Seeders;

use App\Models\StockHistory;
use App\Models\StockRecommendation;
use App\Services\MachineLearning\ModelArtifactLoader;
use App\Services\Recommendation\RecommendationRunService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

class InitialRecommendationSeeder extends Seeder
{
    public function __construct(
        private readonly ModelArtifactLoader $artifactLoader,
        private readonly RecommendationRunService $runService,
    ) {}

    public function run(): void
    {
        $latestDateValue = StockHistory::query()->max('date');
        if ($latestDateValue === null) {
            throw new RuntimeException('Rekomendasi awal tidak dapat dijalankan karena histori stok belum tersedia.');
        }

        $artifact = $this->artifactLoader->load();
        $latestDate = CarbonImmutable::parse($latestDateValue)->toDateString();
        $fingerprint = 'initial-seed:'.hash('sha256', implode('|', [
            $artifact->modelVersion,
            $artifact->fileChecksum,
            $latestDate,
        ]));

        $run = $this->runService->start(
            triggeredBy: null,
            queue: false,
            idempotencyKey: $fingerprint,
        );

        $this->command?->info(sprintf(
            'Rekomendasi awal: run %s, status %s, %d hasil.',
            $run->public_id,
            $run->status->value,
            StockRecommendation::query()->where('recommendation_run_id', $run->id)->count(),
        ));
    }
}
