<?php

use App\Services\MachineLearning\MedianImputer;
use App\Services\MachineLearning\YeoJohnsonTransformer;

it('builds the imputed vector in artifact feature order regardless of associative input order', function () {
    $artifact = loadTestModelArtifact();
    $features = [];

    foreach (array_reverse($artifact->featureOrder) as $index => $name) {
        $features[$name] = 90.0 - $index;
    }

    $vector = (new MedianImputer)->transform($features, $artifact);

    foreach ($artifact->featureOrder as $index => $name) {
        expect($vector[$index])->toBe((float) $features[$name]);
    }
});

it('uses the corresponding median for null missing nan and infinite values', function () {
    $artifact = loadTestModelArtifact();
    $features = array_fill_keys($artifact->featureOrder, 1.0);
    $features['stok_akhir'] = null;
    unset($features['barang_keluar']);
    $features['rata_penjualan_7'] = NAN;
    $features['std_penjualan_7'] = INF;
    $features['rata_penjualan_30'] = -INF;

    $vector = (new MedianImputer)->transform($features, $artifact);

    expect($vector[0])->toBe($artifact->imputerStatistics[0])
        ->and($vector[1])->toBe($artifact->imputerStatistics[1])
        ->and($vector[2])->toBe($artifact->imputerStatistics[2])
        ->and($vector[3])->toBe($artifact->imputerStatistics[3])
        ->and($vector[4])->toBe($artifact->imputerStatistics[4]);
});

it('implements the nonnegative yeo johnson logarithmic branch near lambda zero', function () {
    $actual = (new YeoJohnsonTransformer)->transformValue(3.5, 1.0e-13, 1.0e-12, 1.0e-12);

    expect($actual)->toEqualWithDelta(log1p(3.5), 1.0e-15);
});

it('implements the nonnegative yeo johnson power branch', function () {
    $actual = (new YeoJohnsonTransformer)->transformValue(3.5, 0.4, 1.0e-12, 1.0e-12);
    $expected = (pow(4.5, 0.4) - 1.0) / 0.4;

    expect($actual)->toEqualWithDelta($expected, 1.0e-15);
});

it('implements the negative yeo johnson logarithmic branch near lambda two', function () {
    $actual = (new YeoJohnsonTransformer)->transformValue(-2.5, 2.0 + 1.0e-13, 1.0e-12, 1.0e-12);

    expect($actual)->toEqualWithDelta(-log1p(2.5), 1.0e-15);
});

it('implements the negative yeo johnson power branch', function () {
    $actual = (new YeoJohnsonTransformer)->transformValue(-2.5, 0.4, 1.0e-12, 1.0e-12);
    $expected = -(pow(3.5, 1.6) - 1.0) / 1.6;

    expect($actual)->toEqualWithDelta($expected, 1.0e-15);
});

it('standardizes every transformed feature with artifact means and scales', function () {
    $artifact = loadTestModelArtifact();
    $values = array_fill(0, count($artifact->featureOrder), 2.0);
    $transformer = new YeoJohnsonTransformer;

    $standardized = $transformer->transform($values, $artifact);

    foreach ($standardized as $index => $actual) {
        $transformed = $transformer->transformValue(
            2.0,
            $artifact->lambdas[$index],
            $artifact->lambdaZeroTolerance,
            $artifact->lambdaTwoTolerance,
        );
        $expected = ($transformed - $artifact->scalerMean[$index])
            / $artifact->scalerScale[$index];

        expect($actual)->toEqualWithDelta($expected, 1.0e-12);
    }
});
