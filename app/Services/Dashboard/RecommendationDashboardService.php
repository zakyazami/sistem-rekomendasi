<?php

namespace App\Services\Dashboard;

use App\Domain\Recommendation\Enums\RecommendationItemStatus;
use App\Domain\Recommendation\Enums\RecommendationLabel;
use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Models\Product;
use App\Models\RecommendationRun;
use App\Models\StockHistory;
use App\Services\MachineLearning\ModelArtifactLoader;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class RecommendationDashboardService
{
    public function __construct(private ModelArtifactLoader $artifactLoader) {}

    /** @return array<string, mixed> */
    public function metrics(): array
    {
        $latestDateValue = StockHistory::query()->max('date');
        $latestDate = $latestDateValue === null
            ? null
            : CarbonImmutable::parse($latestDateValue)->toDateString();
        $latestRun = RecommendationRun::query()
            ->latest('created_at')
            ->latest('id')
            ->first();
        $latestCompletedRun = RecommendationRun::query()
            ->where('status', RecommendationRunStatus::Completed->value)
            ->latest('finished_at')
            ->latest('id')
            ->first();
        $recommendations = $latestCompletedRun?->recommendations() ?? null;

        try {
            $artifact = $this->artifactLoader->load();
            $artifactData = [
                'artifact_valid' => true,
                'artifact_error' => null,
                'model_name' => $artifact->modelName,
                'model_version' => $artifact->modelVersion,
                'model_checksum' => $artifact->fileChecksum,
                'threshold' => $artifact->threshold,
                'feature_count' => count($artifact->featureOrder),
                'model_metrics' => $artifact->metrics,
                'training_metadata' => $artifact->trainingMetadata,
            ];
        } catch (Throwable) {
            $artifactData = [
                'artifact_valid' => false,
                'artifact_error' => 'Artifact model tidak valid.',
                'model_name' => null,
                'model_version' => null,
                'model_checksum' => null,
                'threshold' => null,
                'feature_count' => 0,
                'model_metrics' => [],
                'training_metadata' => [],
            ];
        }

        $topPriority = $latestCompletedRun?->recommendations()
            ->with('product.category')
            ->where('final_recommendation', RecommendationLabel::NeedsOrder->value)
            ->orderByDesc('inventory_trigger')
            ->orderByDesc('recommended_quantity')
            ->orderByDesc('model_probability_positive')
            ->limit(10)
            ->get() ?? collect();

        return [
            'active_products' => Product::query()->where('is_active', true)->count(),
            'latest_data_date' => $latestDate,
            'data_is_stale' => $latestDate === null
                || CarbonImmutable::parse($latestDate)->diffInDays(CarbonImmutable::now()) > 7,
            'latest_run' => $latestRun,
            'latest_completed_run' => $latestCompletedRun,
            'needs_order' => $recommendations?->where('final_recommendation', RecommendationLabel::NeedsOrder->value)->count() ?? 0,
            'total_recommended_quantity' => (int) ($recommendations?->sum('recommended_quantity') ?? 0),
            'insufficient_data' => $recommendations?->where('item_status', RecommendationItemStatus::InsufficientData->value)->count() ?? 0,
            'top_priority' => $topPriority,
            ...$artifactData,
        ];
    }
}
