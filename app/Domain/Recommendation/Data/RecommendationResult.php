<?php

namespace App\Domain\Recommendation\Data;

use App\Domain\Recommendation\Enums\RecommendationLabel;

final readonly class RecommendationResult
{
    /**
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $warnings
     */
    public function __construct(
        public FeatureVector $featureVector,
        public ModelPrediction $prediction,
        public float $inventoryPosition,
        public float $projectedInventory,
        public int $safetyStock,
        public int $reorderPoint,
        public int $targetStock,
        public bool $inventoryTrigger,
        public RecommendationLabel $finalLabel,
        public int $recommendedQuantity,
        public array $reasonCodes,
        public array $warnings,
    ) {}
}
