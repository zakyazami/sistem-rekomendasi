<?php

use App\Domain\Recommendation\Data\ModelArtifact;
use App\Services\MachineLearning\GaussianNaiveBayesClassifier;
use App\Services\MachineLearning\MedianImputer;
use App\Services\MachineLearning\NaiveBayesInferenceService;
use App\Services\MachineLearning\YeoJohnsonTransformer;

function modelArtifactWithThreshold(ModelArtifact $artifact, float $threshold): ModelArtifact
{
    return new ModelArtifact(
        schemaVersion: $artifact->schemaVersion,
        artifactType: $artifact->artifactType,
        modelName: $artifact->modelName,
        modelVersion: $artifact->modelVersion,
        fileChecksum: $artifact->fileChecksum,
        payloadChecksum: $artifact->payloadChecksum,
        featureOrder: $artifact->featureOrder,
        imputerStatistics: $artifact->imputerStatistics,
        lambdas: $artifact->lambdas,
        scalerMean: $artifact->scalerMean,
        scalerScale: $artifact->scalerScale,
        lambdaZeroTolerance: $artifact->lambdaZeroTolerance,
        lambdaTwoTolerance: $artifact->lambdaTwoTolerance,
        classes: $artifact->classes,
        classPrior: $artifact->classPrior,
        theta: $artifact->theta,
        variance: $artifact->variance,
        positiveClass: $artifact->positiveClass,
        labels: $artifact->labels,
        threshold: $threshold,
        inventoryDefaults: $artifact->inventoryDefaults,
        metrics: $artifact->metrics,
        trainingMetadata: $artifact->trainingMetadata,
        raw: $artifact->raw,
    );
}

it('calculates gaussian naive bayes joint log likelihood without rounding', function () {
    $artifact = loadTestModelArtifact();
    $features = array_fill(0, count($artifact->featureOrder), 0.0);
    $expected = [];

    foreach ($artifact->classes as $classIndex => $_class) {
        $value = log($artifact->classPrior[$classIndex]);

        foreach ($features as $featureIndex => $feature) {
            $variance = $artifact->variance[$classIndex][$featureIndex];
            $theta = $artifact->theta[$classIndex][$featureIndex];
            $value += -0.5 * log(2.0 * M_PI * $variance);
            $value += -0.5 * (($feature - $theta) ** 2 / $variance);
        }

        $expected[] = $value;
    }

    $actual = (new GaussianNaiveBayesClassifier)->jointLogLikelihood($features, $artifact);

    expect($actual[0])->toEqualWithDelta($expected[0], 1.0e-14)
        ->and($actual[1])->toEqualWithDelta($expected[1], 1.0e-14);
});

it('uses stable softmax for very negative joint log likelihood values', function () {
    $probabilities = (new GaussianNaiveBayesClassifier)->softmax([-10000.0, -10001.0]);
    $denominator = 1.0 + exp(-1.0);

    expect($probabilities[0])->toEqualWithDelta(1.0 / $denominator, 1.0e-15)
        ->and($probabilities[1])->toEqualWithDelta(exp(-1.0) / $denominator, 1.0e-15)
        ->and(array_sum($probabilities))->toEqualWithDelta(1.0, 1.0e-15);
});

it('treats a probability exactly equal to the threshold as positive', function () {
    $artifact = loadTestModelArtifact();
    $classifier = new GaussianNaiveBayesClassifier;
    $features = array_fill(0, count($artifact->featureOrder), 0.0);
    $probability = $classifier->softmax(
        $classifier->jointLogLikelihood($features, $artifact),
    )[1];

    $prediction = $classifier->predict(
        $features,
        modelArtifactWithThreshold($artifact, $probability),
    );

    expect($prediction->isPositive)->toBeTrue()
        ->and($prediction->predictedClass)->toBe(1)
        ->and($prediction->predictedLabel)->toBe('Perlu Pesan');
});

it('produces deterministic full precision predictions through the inference service', function () {
    $artifact = loadTestModelArtifact();
    $features = [
        'stok_akhir' => 12.0,
        'barang_keluar' => 4.0,
        'rata_penjualan_7' => 1.857143,
        'std_penjualan_7' => 2.531435,
        'rata_penjualan_30' => 2.966667,
        'std_penjualan_30' => 2.651834,
        'cakupan_stok_hari' => 4.044944,
        'hari_dalam_minggu' => 3.0,
        'horizon_hari_target' => 1.0,
    ];
    $service = new NaiveBayesInferenceService(
        new MedianImputer,
        new YeoJohnsonTransformer,
        new GaussianNaiveBayesClassifier,
    );

    $first = $service->predict($features, $artifact);
    $second = $service->predict(array_reverse($features, true), $artifact);

    expect($first)->toEqual($second)
        ->and($first->probabilities)->toHaveCount(2)
        ->and(array_sum($first->probabilities))->toEqualWithDelta(1.0, 1.0e-14)
        ->and($first->positiveProbability)->not->toBe(round($first->positiveProbability, 6));
});
