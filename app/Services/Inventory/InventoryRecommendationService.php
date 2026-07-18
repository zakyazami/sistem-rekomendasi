<?php

namespace App\Services\Inventory;

use App\Contracts\RecommendationEngine;
use App\Domain\Recommendation\Data\FeatureVector;
use App\Domain\Recommendation\Data\InventoryParameters;
use App\Domain\Recommendation\Data\RecommendationResult;
use App\Models\StockHistory;
use App\Services\MachineLearning\ModelArtifactLoader;
use App\Services\MachineLearning\NaiveBayesInferenceService;

final readonly class InventoryRecommendationService implements RecommendationEngine
{
    public function __construct(
        private InventoryFeatureEngineeringService $featureEngineering,
        private NaiveBayesInferenceService $inference,
        private InventoryPolicyService $policy,
        private ModelArtifactLoader $artifactLoader,
    ) {}

    public function recommendFeatures(
        FeatureVector $features,
        InventoryParameters $parameters,
    ): RecommendationResult {
        $artifact = $this->artifactLoader->load();
        $prediction = $this->inference->predict($features->values, $artifact);

        return $this->policy->evaluate($features, $prediction, $parameters, $artifact);
    }

    /** @param iterable<int, StockHistory> $histories */
    public function recommendHistories(
        iterable $histories,
        InventoryParameters $parameters,
    ): ?RecommendationResult {
        $features = $this->featureEngineering->build($histories, $parameters->horizonDays);

        return $features === null
            ? null
            : $this->recommendFeatures($features, $parameters);
    }
}
