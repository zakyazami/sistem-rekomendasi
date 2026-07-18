<?php

namespace App\Services\Dashboard;

use App\Domain\Recommendation\Enums\RecommendationLabel;
use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Models\RecommendationRun;
use App\Models\StockRecommendation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class LatestRecommendationQuery
{
    public function latestCompletedRun(): ?RecommendationRun
    {
        return RecommendationRun::query()
            ->where('status', RecommendationRunStatus::Completed->value)
            ->latest('finished_at')
            ->latest('id')
            ->first();
    }

    /** @return Collection<int, StockRecommendation> */
    public function topPriority(int $limit = 10): Collection
    {
        return $this->queryForLatestCompleted()
            ->limit($limit)
            ->get();
    }

    /** @return Builder<StockRecommendation> */
    public function queryForLatestCompleted(): Builder
    {
        $latestRunId = $this->latestCompletedRun()?->id;

        return StockRecommendation::query()
            ->with(['product.category'])
            ->when($latestRunId === null, fn (Builder $query): Builder => $query->whereRaw('1 = 0'))
            ->when($latestRunId !== null, fn (Builder $query): Builder => $query->where('recommendation_run_id', $latestRunId))
            ->where('final_recommendation', RecommendationLabel::NeedsOrder->value)
            ->orderByDesc('recommended_quantity')
            ->orderByDesc('model_probability_positive')
            ->orderByDesc('inventory_trigger');
    }

    public function triggerLabel(StockRecommendation $recommendation): string
    {
        $modelTrigger = $recommendation->model_classification === RecommendationLabel::NeedsOrder->value;
        $stockTrigger = $recommendation->inventory_trigger;

        return match (true) {
            $modelTrigger && $stockTrigger => 'Keduanya',
            $modelTrigger => 'Trigger Model',
            $stockTrigger => 'Trigger Stok',
            default => 'Aturan Persediaan',
        };
    }
}
