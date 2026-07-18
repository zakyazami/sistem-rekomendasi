<?php

use App\Domain\Recommendation\Data\FeatureVector;
use App\Domain\Recommendation\Data\InventoryParameters;
use App\Domain\Recommendation\Enums\RecommendationLabel;
use App\Services\Inventory\InventoryFeatureEngineeringService;
use App\Services\Inventory\InventoryPolicyService;
use App\Services\Inventory\InventoryRecommendationService;
use App\Services\Inventory\NormalDistributionQuantile;
use App\Services\MachineLearning\GaussianNaiveBayesClassifier;
use App\Services\MachineLearning\MedianImputer;
use App\Services\MachineLearning\ModelArtifactLoader;
use App\Services\MachineLearning\NaiveBayesInferenceService;
use App\Services\MachineLearning\YeoJohnsonTransformer;
use Carbon\CarbonImmutable;

function recommendationFeatureVector(float $stock, float $average30, float $std30): FeatureVector
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

function inventoryRecommendationService(): InventoryRecommendationService
{
    $root = dirname(__DIR__, 3);

    return new InventoryRecommendationService(
        new InventoryFeatureEngineeringService,
        new NaiveBayesInferenceService(
            new MedianImputer,
            new YeoJohnsonTransformer,
            new GaussianNaiveBayesClassifier,
        ),
        new InventoryPolicyService(new NormalDistributionQuantile),
        new ModelArtifactLoader(
            $root.'/resources/ml/naive_bayes_rekomendasi_stok_laravel.json',
            $root.'/resources/ml/naive_bayes_rekomendasi_stok_laravel.json.sha256',
        ),
    );
}

it('combines native inference and inventory policy for an engineered feature vector', function () {
    $result = inventoryRecommendationService()->recommendFeatures(
        recommendationFeatureVector(11.0, 3.3, 2.5),
        InventoryParameters::defaults(),
    );

    expect($result->prediction->probabilities)->toHaveCount(2)
        ->and($result->inventoryTrigger)->toBeTrue()
        ->and($result->finalLabel)->toBe(RecommendationLabel::NeedsOrder)
        ->and($result->recommendedQuantity)->toBe(34);
});

it('returns null without inventing a probability when history is insufficient', function () {
    expect(inventoryRecommendationService()->recommendHistories(
        [],
        InventoryParameters::defaults(),
    ))->toBeNull();
});
