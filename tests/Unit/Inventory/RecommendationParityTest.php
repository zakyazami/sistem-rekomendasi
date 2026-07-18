<?php

use App\Domain\Recommendation\Data\FeatureVector;
use App\Domain\Recommendation\Data\InventoryParameters;
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

dataset('recommendation parity rows', function () {
    $path = dirname(__DIR__, 3).'/tests/Fixtures/ML/parity_test_rekomendasi_laravel.csv';
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

        yield $row['nama_barang'] => [$row];
    }
});

it('matches the notebook hybrid recommendation for every product', function (array $row) {
    $root = dirname(__DIR__, 3);
    $service = new InventoryRecommendationService(
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
    $features = new FeatureVector(
        values: [
            'stok_akhir' => (float) $row['stok_akhir'],
            'barang_keluar' => (float) $row['barang_keluar'],
            'rata_penjualan_7' => (float) $row['rata_penjualan_7'],
            'std_penjualan_7' => (float) $row['std_penjualan_7'],
            'rata_penjualan_30' => (float) $row['rata_penjualan_30'],
            'std_penjualan_30' => (float) $row['std_penjualan_30'],
            'cakupan_stok_hari' => $row['cakupan_stok_hari'] === '' ? null : (float) $row['cakupan_stok_hari'],
            'hari_dalam_minggu' => (float) $row['hari_dalam_minggu'],
            'horizon_hari_target' => (float) $row['horizon_hari_target'],
        ],
        dataDate: CarbonImmutable::parse($row['tanggal_data_terakhir']),
        historyCount: 31,
        currentStock: (float) $row['stok_saat_ini'],
        currentOutgoingStock: (float) $row['barang_keluar'],
    );
    $parameters = new InventoryParameters(
        leadTimeDays: (int) $row['lead_time_hari'],
        reviewPeriodDays: (int) $row['review_period_hari'],
        serviceLevel: (float) $row['service_level'],
        horizonDays: (int) $row['horizon_hari_target'],
        onOrderQuantity: (int) $row['barang_dalam_pemesanan_input'],
    );

    $actual = $service->recommendFeatures($features, $parameters);

    // Notebook intentionally serializes these report fields after rounding.
    expect(round($actual->prediction->positiveProbability, 6))->toBe((float) $row['probabilitas_perlu_pesan'])
        ->and($actual->prediction->predictedLabel)->toBe($row['klasifikasi_model'])
        ->and(round($actual->projectedInventory, 2))->toBe((float) $row['projected_inventory'])
        ->and($actual->reorderPoint)->toBe((int) $row['reorder_point_expected'])
        ->and($actual->targetStock)->toBe((int) $row['target_stock_expected'])
        ->and($actual->inventoryTrigger)->toBe($row['trigger_inventory'] === 'Ya')
        ->and($actual->finalLabel->value)->toBe($row['rekomendasi_final'])
        ->and($actual->recommendedQuantity)->toBe((int) $row['jumlah_stok_harus_dipesan']);
})->with('recommendation parity rows');
