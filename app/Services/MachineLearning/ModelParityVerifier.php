<?php

namespace App\Services\MachineLearning;

use App\Domain\Recommendation\Data\FeatureVector;
use App\Domain\Recommendation\Data\InventoryParameters;
use App\Services\Inventory\InventoryRecommendationService;
use Carbon\CarbonImmutable;
use RuntimeException;
use SplFileObject;

final readonly class ModelParityVerifier
{
    public function __construct(
        private ModelArtifactLoader $artifactLoader,
        private NaiveBayesInferenceService $inference,
        private InventoryRecommendationService $recommendations,
    ) {}

    /** @return array{model: int, recommendations: int} */
    public function verify(): array
    {
        return [
            'model' => $this->verifyModel(base_path('tests/Fixtures/ML/parity_test_model_laravel.csv')),
            'recommendations' => $this->verifyRecommendations(base_path('tests/Fixtures/ML/parity_test_rekomendasi_laravel.csv')),
        ];
    }

    private function verifyModel(string $path): int
    {
        $artifact = $this->artifactLoader->load();
        $count = 0;

        foreach ($this->rows($path) as $row) {
            $features = [];
            foreach ($artifact->featureOrder as $feature) {
                $features[$feature] = $row["input__{$feature}"] === ''
                    ? null
                    : (float) $row["input__{$feature}"];
            }

            $prediction = $this->inference->predict($features, $artifact);
            foreach ($artifact->featureOrder as $index => $feature) {
                $this->assertNear(
                    $prediction->transformedFeatures[$index],
                    (float) $row["transformed__{$feature}"],
                    "transform {$feature}",
                );
            }

            foreach ([0, 1] as $class) {
                $this->assertNear(
                    $prediction->jointLogLikelihoods[$class],
                    (float) $row["expected_joint_log_likelihood_{$class}"],
                    "jll {$class}",
                );
                $this->assertNear(
                    $prediction->probabilities[$class],
                    (float) $row["expected_probability_{$class}"],
                    "probability {$class}",
                );
            }

            if ($prediction->predictedClass !== (int) $row['expected_prediction_class']
                || $prediction->predictedLabel !== $row['expected_prediction_label']) {
                throw new RuntimeException("Parity label gagal untuk {$row['nama_barang']}.");
            }

            $count++;
        }

        return $count;
    }

    private function verifyRecommendations(string $path): int
    {
        $count = 0;

        foreach ($this->rows($path) as $row) {
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
            $actual = $this->recommendations->recommendFeatures($features, $parameters);

            if (round($actual->prediction->positiveProbability, 6) !== (float) $row['probabilitas_perlu_pesan']
                || round($actual->projectedInventory, 2) !== (float) $row['projected_inventory']
                || $actual->prediction->predictedLabel !== $row['klasifikasi_model']
                || $actual->inventoryTrigger !== ($row['trigger_inventory'] === 'Ya')
                || $actual->reorderPoint !== (int) $row['reorder_point_expected']
                || $actual->targetStock !== (int) $row['target_stock_expected']
                || $actual->finalLabel->value !== $row['rekomendasi_final']
                || $actual->recommendedQuantity !== (int) $row['jumlah_stok_harus_dipesan']) {
                throw new RuntimeException("Parity rekomendasi gagal untuk {$row['nama_barang']}.");
            }

            $count++;
        }

        return $count;
    }

    /** @return iterable<int, array<string, string>> */
    private function rows(string $path): iterable
    {
        if (! is_file($path)) {
            throw new RuntimeException('Fixture parity tidak ditemukan.');
        }

        $file = new SplFileObject($path);
        $file->setCsvControl(',', '"', '');
        $headers = $file->fgetcsv();

        while (! $file->eof()) {
            $values = $file->fgetcsv();
            if (! is_array($values) || $values === [null] || count($values) !== count($headers)) {
                continue;
            }

            yield array_combine($headers, $values);
        }
    }

    private function assertNear(float $actual, float $expected, string $field): void
    {
        if (abs($actual - $expected) > 1.0e-8) {
            throw new RuntimeException("Parity {$field} melewati tolerance 1e-8.");
        }
    }
}
