<?php

use App\Domain\Recommendation\Data\FeatureVector;
use App\Domain\Recommendation\Data\InventoryParameters;
use App\Domain\Recommendation\Data\ModelPrediction;
use App\Domain\Recommendation\Enums\RecommendationLabel;
use App\Services\Inventory\InventoryPolicyService;
use App\Services\Inventory\NormalDistributionQuantile;
use Carbon\CarbonImmutable;

function featureVectorForPolicy(float $stock, float $average30, float $std30): FeatureVector
{
    return new FeatureVector(
        values: [
            'stok_akhir' => $stock,
            'barang_keluar' => 3.0,
            'rata_penjualan_7' => $average30,
            'std_penjualan_7' => $std30,
            'rata_penjualan_30' => $average30,
            'std_penjualan_30' => $std30,
            'cakupan_stok_hari' => $average30 > 0 ? $stock / $average30 : null,
            'hari_dalam_minggu' => 6.0,
            'horizon_hari_target' => 1.0,
        ],
        dataDate: CarbonImmutable::parse('2025-08-31'),
        historyCount: 31,
        currentStock: $stock,
        currentOutgoingStock: 3.0,
    );
}

function modelPredictionForPolicy(bool $positive, float $probability = 0.5): ModelPrediction
{
    return new ModelPrediction(
        transformedFeatures: array_fill(0, 9, 0.0),
        jointLogLikelihoods: [-10.0, -11.0],
        probabilities: [1.0 - $probability, $probability],
        positiveProbability: $probability,
        predictedClass: $positive ? 1 : 0,
        predictedLabel: $positive ? 'Perlu Pesan' : 'Tidak',
        isPositive: $positive,
    );
}

it('calculates inventory values and triggers a hybrid recommendation transparently', function () {
    $artifact = loadTestModelArtifact();
    $result = (new InventoryPolicyService(new NormalDistributionQuantile))->evaluate(
        featureVectorForPolicy(11.0, 3.3, 2.5),
        modelPredictionForPolicy(true, 0.999777),
        InventoryParameters::defaults(),
        $artifact,
    );

    expect($result->inventoryPosition)->toBe(11.0)
        ->and($result->projectedInventory)->toEqualWithDelta(7.7, 1.0e-12)
        ->and($result->safetyStock)->toBe(8)
        ->and($result->reorderPoint)->toBe(18)
        ->and($result->targetStock)->toBe(41)
        ->and($result->inventoryTrigger)->toBeTrue()
        ->and($result->finalLabel)->toBe(RecommendationLabel::NeedsOrder)
        ->and($result->recommendedQuantity)->toBe(34)
        ->and($result->reasonCodes)->toContain('inventory_at_or_below_reorder_point', 'model_above_threshold');
});

it('can recommend from a positive model before the inventory trigger', function () {
    $artifact = loadTestModelArtifact();
    $parameters = new InventoryParameters(leadTimeDays: 1, reviewPeriodDays: 20, serviceLevel: 0.95, horizonDays: 1, onOrderQuantity: 0);
    $result = (new InventoryPolicyService(new NormalDistributionQuantile))->evaluate(
        featureVectorForPolicy(20.0, 1.0, 0.0),
        modelPredictionForPolicy(true, 0.995),
        $parameters,
        $artifact,
    );

    expect($result->projectedInventory)->toBe(19.0)
        ->and($result->reorderPoint)->toBe(1)
        ->and($result->inventoryTrigger)->toBeFalse()
        ->and($result->finalLabel)->toBe(RecommendationLabel::NeedsOrder)
        ->and($result->recommendedQuantity)->toBe(2);
});

it('does not order when neither model nor inventory policy triggers', function () {
    $artifact = loadTestModelArtifact();
    $result = (new InventoryPolicyService(new NormalDistributionQuantile))->evaluate(
        featureVectorForPolicy(100.0, 1.0, 0.0),
        modelPredictionForPolicy(false, 0.01),
        InventoryParameters::defaults(),
        $artifact,
    );

    expect($result->inventoryTrigger)->toBeFalse()
        ->and($result->finalLabel)->toBe(RecommendationLabel::NoOrder)
        ->and($result->recommendedQuantity)->toBe(0);
});

it('treats projected inventory equal to reorder point as an inventory trigger', function () {
    $artifact = loadTestModelArtifact();
    $parameters = new InventoryParameters(leadTimeDays: 1, reviewPeriodDays: 1, serviceLevel: 0.95, horizonDays: 1, onOrderQuantity: 0);
    $result = (new InventoryPolicyService(new NormalDistributionQuantile))->evaluate(
        featureVectorForPolicy(2.0, 1.0, 0.0),
        modelPredictionForPolicy(false),
        $parameters,
        $artifact,
    );

    expect($result->projectedInventory)->toBe(1.0)
        ->and($result->reorderPoint)->toBe(1)
        ->and($result->inventoryTrigger)->toBeTrue()
        ->and($result->recommendedQuantity)->toBe(1);
});

it('guards against a needs order label with zero quantity', function () {
    $artifact = loadTestModelArtifact();
    $result = (new InventoryPolicyService(new NormalDistributionQuantile))->evaluate(
        featureVectorForPolicy(0.0, 0.0, 0.0),
        modelPredictionForPolicy(false),
        InventoryParameters::defaults(),
        $artifact,
    );

    expect($result->inventoryTrigger)->toBeTrue()
        ->and($result->recommendedQuantity)->toBe(0)
        ->and($result->finalLabel)->toBe(RecommendationLabel::NoOrder)
        ->and($result->warnings)->toContain('Pemicu persediaan aktif tetapi jumlah pesan adalah nol.');
});

it('uses the artifact z score at the default level and an accurate inverse cdf otherwise', function () {
    $quantile = new NormalDistributionQuantile;

    expect($quantile->forServiceLevel(0.95, loadTestModelArtifact()))
        ->toBe(1.6448536269514722)
        ->and($quantile->forServiceLevel(0.5, loadTestModelArtifact()))
        ->toEqualWithDelta(0.0, 1.0e-12)
        ->and($quantile->forServiceLevel(0.975, loadTestModelArtifact()))
        ->toEqualWithDelta(1.959963984540054, 1.0e-8);
});
