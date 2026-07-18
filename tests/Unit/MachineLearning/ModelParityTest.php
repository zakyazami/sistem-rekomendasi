<?php

use App\Services\MachineLearning\GaussianNaiveBayesClassifier;
use App\Services\MachineLearning\MedianImputer;
use App\Services\MachineLearning\NaiveBayesInferenceService;
use App\Services\MachineLearning\YeoJohnsonTransformer;

dataset('model parity vectors', function () {
    $path = dirname(__DIR__, 3).'/tests/Fixtures/ML/parity_test_model_laravel.csv';
    $file = new SplFileObject($path);
    $file->setCsvControl(',', '"', '');
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
    $headers = $file->fgetcsv();

    while (! $file->eof()) {
        $values = $file->fgetcsv();

        if (! is_array($values) || $values === [null] || count($values) !== count($headers)) {
            continue;
        }

        $row = array_combine($headers, $values);
        $name = sprintf('%s %s', $row['tanggal_target'], $row['nama_barang']);

        yield $name => [$row];
    }
});

it('matches every transformed feature jll probability and model label from the notebook', function (array $row) {
    $artifact = loadTestModelArtifact();
    $features = [];

    foreach ($artifact->featureOrder as $feature) {
        $features[$feature] = $row["input__{$feature}"] === ''
            ? null
            : (float) $row["input__{$feature}"];
    }

    $prediction = (new NaiveBayesInferenceService(
        new MedianImputer,
        new YeoJohnsonTransformer,
        new GaussianNaiveBayesClassifier,
    ))->predict($features, $artifact);

    foreach ($artifact->featureOrder as $index => $feature) {
        expect($prediction->transformedFeatures[$index])->toEqualWithDelta(
            (float) $row["transformed__{$feature}"],
            1.0e-8,
        );
    }

    expect($prediction->jointLogLikelihoods[0])->toEqualWithDelta(
        (float) $row['expected_joint_log_likelihood_0'],
        1.0e-8,
    )->and($prediction->jointLogLikelihoods[1])->toEqualWithDelta(
        (float) $row['expected_joint_log_likelihood_1'],
        1.0e-8,
    )->and($prediction->probabilities[0])->toEqualWithDelta(
        (float) $row['expected_probability_0'],
        1.0e-8,
    )->and($prediction->probabilities[1])->toEqualWithDelta(
        (float) $row['expected_probability_1'],
        1.0e-8,
    )->and($prediction->predictedClass)->toBe((int) $row['expected_prediction_class'])
        ->and($prediction->predictedLabel)->toBe($row['expected_prediction_label']);
})->with('model parity vectors');
