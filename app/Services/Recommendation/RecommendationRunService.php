<?php

namespace App\Services\Recommendation;

use App\Domain\Recommendation\Data\InventoryParameters;
use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Jobs\ProcessRecommendationRun;
use App\Models\RecommendationRun;
use App\Models\User;
use App\Services\MachineLearning\ModelArtifactLoader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RecommendationRunService
{
    public function __construct(
        private ModelArtifactLoader $artifactLoader,
        private ModelVersionRegistry $modelRegistry,
        private RecommendationRunProcessor $processor,
    ) {}

    public function start(
        ?User $triggeredBy,
        bool $queue = true,
        ?string $idempotencyKey = null,
        ?RecommendationRun $retryOf = null,
    ): RecommendationRun {
        $idempotencyKey ??= (string) Str::uuid();
        $existing = RecommendationRun::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing instanceof RecommendationRun) {
            return $existing;
        }

        $artifact = $this->artifactLoader->load();
        $modelVersion = $this->modelRegistry->activate($artifact);
        $defaults = InventoryParameters::defaults();

        $run = DB::transaction(fn (): RecommendationRun => RecommendationRun::query()->create([
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => $idempotencyKey,
            'status' => RecommendationRunStatus::Pending,
            'triggered_by' => $triggeredBy?->id,
            'model_version_id' => $modelVersion->id,
            'retry_of_id' => $retryOf?->id,
            'total_products' => 0,
            'parameter_snapshot' => [
                'lead_time_days' => $defaults->leadTimeDays,
                'review_period_days' => $defaults->reviewPeriodDays,
                'service_level' => $defaults->serviceLevel,
                'prediction_horizon_days' => $defaults->horizonDays,
                'artifact_checksum' => $artifact->fileChecksum,
            ],
        ]));

        if ($queue) {
            ProcessRecommendationRun::dispatch($run->id)->onQueue('recommendations');

            return $run;
        }

        return $this->processor->process($run);
    }
}
