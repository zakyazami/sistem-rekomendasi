<?php

namespace App\Services\Inventory;

use App\Domain\Recommendation\Data\FeatureVector;
use App\Domain\Recommendation\Data\InventoryParameters;
use App\Domain\Recommendation\Data\ModelArtifact;
use App\Domain\Recommendation\Data\ModelPrediction;
use App\Domain\Recommendation\Data\RecommendationResult;
use App\Domain\Recommendation\Enums\RecommendationLabel;

final readonly class InventoryPolicyService
{
    public function __construct(private NormalDistributionQuantile $quantile) {}

    public function evaluate(
        FeatureVector $features,
        ModelPrediction $prediction,
        InventoryParameters $parameters,
        ModelArtifact $artifact,
    ): RecommendationResult {
        $average30 = (float) $features->values['rata_penjualan_30'];
        $standardDeviation30 = (float) $features->values['std_penjualan_30'];
        $z = $this->quantile->forServiceLevel($parameters->serviceLevel, $artifact);
        $rawSafetyStock = $z * $standardDeviation30 * sqrt($parameters->leadTimeDays);
        $safetyStock = (int) ceil($rawSafetyStock);
        $reorderPoint = (int) ceil(
            ($average30 * $parameters->leadTimeDays) + $rawSafetyStock,
        );
        $targetStock = (int) ceil(
            ($average30 * ($parameters->leadTimeDays + $parameters->reviewPeriodDays))
            + $rawSafetyStock,
        );
        $inventoryPosition = $features->currentStock + $parameters->onOrderQuantity;
        $projectedInventory = max(
            0.0,
            $inventoryPosition - ($average30 * $parameters->horizonDays),
        );
        $inventoryTrigger = $projectedInventory <= $reorderPoint;
        $inventoryQuantity = max(0, (int) ceil($targetStock - $projectedInventory));
        $finalNeed = $inventoryTrigger || ($prediction->isPositive && $inventoryQuantity > 0);
        $warnings = [];

        if ($finalNeed && $inventoryQuantity === 0) {
            $finalNeed = false;
            $warnings[] = 'Pemicu persediaan aktif tetapi jumlah pesan adalah nol.';
        }

        $reasonCodes = [];
        if ($inventoryTrigger) {
            $reasonCodes[] = 'inventory_at_or_below_reorder_point';
        }
        if ($prediction->isPositive) {
            $reasonCodes[] = 'model_above_threshold';
        }
        if (! $finalNeed) {
            $reasonCodes[] = 'no_order_required';
        }

        return new RecommendationResult(
            featureVector: $features,
            prediction: $prediction,
            inventoryPosition: $inventoryPosition,
            projectedInventory: $projectedInventory,
            safetyStock: $safetyStock,
            reorderPoint: $reorderPoint,
            targetStock: $targetStock,
            inventoryTrigger: $inventoryTrigger,
            finalLabel: $finalNeed ? RecommendationLabel::NeedsOrder : RecommendationLabel::NoOrder,
            recommendedQuantity: $finalNeed ? $inventoryQuantity : 0,
            reasonCodes: $reasonCodes,
            warnings: $warnings,
        );
    }
}
