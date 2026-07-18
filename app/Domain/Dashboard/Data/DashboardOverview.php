<?php

namespace App\Domain\Dashboard\Data;

use App\Models\RecommendationRun;

final readonly class DashboardOverview
{
    /**
     * @param  array<string, array{label: string, value: string, tooltip: string}>  $mainMetrics
     * @param  array<string, array{label: string, value: string, tooltip: string}>  $advancedMetrics
     */
    public function __construct(
        public int $activeProducts,
        public string $activeProductsLabel,
        public ?string $latestDataDate,
        public string $latestDataDateLabel,
        public string $freshnessLabel,
        public string $freshnessColor,
        public ?int $needsOrder,
        public string $needsOrderLabel,
        public ?int $totalRecommendedQuantity,
        public string $totalRecommendedQuantityLabel,
        public bool $artifactValid,
        public string $artifactStatusLabel,
        public string $artifactColor,
        public string $modelName,
        public string $modelVersionLabel,
        public string $thresholdLabel,
        public string $trainedAtLabel,
        public string $checksumShort,
        public ?string $checksumFull,
        public array $mainMetrics,
        public array $advancedMetrics,
        public ?RecommendationRun $latestRun,
        public string $runStatusLabel,
        public string $runStatusColor,
        public string $runTimeLabel,
        public string $runDurationLabel,
        public int $runProcessedProducts,
        public int $runNeedsOrder,
        public ?string $runError,
        public string $nextAction,
        public string $emptyStateHeading,
        public string $emptyStateDescription,
    ) {}
}
