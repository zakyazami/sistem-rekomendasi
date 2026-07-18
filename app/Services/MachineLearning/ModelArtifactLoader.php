<?php

namespace App\Services\MachineLearning;

use App\Domain\Recommendation\Data\ModelArtifact;
use App\Exceptions\InvalidModelArtifactException;
use JsonException;

final class ModelArtifactLoader
{
    /** @var list<string> */
    public const FEATURE_ORDER = [
        'stok_akhir',
        'barang_keluar',
        'rata_penjualan_7',
        'std_penjualan_7',
        'rata_penjualan_30',
        'std_penjualan_30',
        'cakupan_stok_hari',
        'hari_dalam_minggu',
        'horizon_hari_target',
    ];

    private ?ModelArtifact $loaded = null;

    public function __construct(
        private readonly string $artifactPath,
        private readonly string $checksumPath,
    ) {}

    public function load(): ModelArtifact
    {
        if ($this->loaded instanceof ModelArtifact) {
            return $this->loaded;
        }

        $rawBytes = $this->readArtifact();
        $expectedChecksum = $this->readChecksum();
        $actualChecksum = hash('sha256', $rawBytes);

        if (! hash_equals($expectedChecksum, $actualChecksum)) {
            throw new InvalidModelArtifactException('Checksum artifact model tidak cocok.');
        }

        try {
            $data = json_decode($rawBytes, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidModelArtifactException(
                'JSON artifact model tidak valid.',
                previous: $exception,
            );
        }

        if (! is_array($data)) {
            throw new InvalidModelArtifactException('JSON artifact model tidak valid.');
        }

        $this->validate($data);

        $power = $data['preprocessing']['power_transformer'];
        $classifier = $data['classifier'];
        $target = $data['target'];

        return $this->loaded = new ModelArtifact(
            schemaVersion: $data['schema_version'],
            artifactType: $data['artifact_type'],
            modelName: $data['model_name'],
            modelVersion: $data['model_version'],
            fileChecksum: $actualChecksum,
            payloadChecksum: $data['artifact_sha256'] ?? null,
            featureOrder: $data['feature_order'],
            imputerStatistics: $this->floats($data['preprocessing']['imputer']['statistics']),
            lambdas: $this->floats($power['lambdas']),
            scalerMean: $this->floats($power['scaler_mean']),
            scalerScale: $this->floats($power['scaler_scale']),
            lambdaZeroTolerance: (float) $power['lambda_zero_tolerance'],
            lambdaTwoTolerance: (float) $power['lambda_two_tolerance'],
            classes: $classifier['classes'],
            classPrior: $this->floats($classifier['class_prior']),
            theta: array_map($this->floats(...), $classifier['theta']),
            variance: array_map($this->floats(...), $classifier['variance']),
            positiveClass: $target['positive_class'],
            labels: $target['labels'],
            threshold: (float) $data['decision']['probability_threshold'],
            inventoryDefaults: $data['inventory_defaults'],
            metrics: $data['metrics_test'],
            trainingMetadata: $data['training_metadata'],
            raw: $data,
        );
    }

    private function readArtifact(): string
    {
        if (! is_file($this->artifactPath) || ! is_readable($this->artifactPath)) {
            throw new InvalidModelArtifactException('Artifact model tidak ditemukan.');
        }

        $contents = file_get_contents($this->artifactPath);

        if ($contents === false) {
            throw new InvalidModelArtifactException('Artifact model tidak dapat dibaca.');
        }

        return $contents;
    }

    private function readChecksum(): string
    {
        if (! is_file($this->checksumPath) || ! is_readable($this->checksumPath)) {
            throw new InvalidModelArtifactException('Checksum artifact model tidak ditemukan.');
        }

        $contents = file_get_contents($this->checksumPath);

        if ($contents === false || ! preg_match('/^([a-f0-9]{64})(?:\s|$)/i', trim($contents), $matches)) {
            throw new InvalidModelArtifactException('Format checksum artifact tidak valid.');
        }

        return strtolower($matches[1]);
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): void
    {
        if (($data['schema_version'] ?? null) !== '1.0.0') {
            throw new InvalidModelArtifactException('Versi schema artifact tidak didukung.');
        }

        if (($data['artifact_type'] ?? null) !== 'gaussian_naive_bayes_php_inference') {
            throw new InvalidModelArtifactException('Tipe artifact model tidak didukung.');
        }

        if (($data['feature_order'] ?? null) !== self::FEATURE_ORDER) {
            throw new InvalidModelArtifactException('Urutan fitur artifact tidak valid.');
        }

        if (($data['preprocessing']['imputer']['strategy'] ?? null) !== 'median'
            || ($data['preprocessing']['power_transformer']['method'] ?? null) !== 'yeo-johnson'
            || ($data['classifier']['type'] ?? null) !== 'GaussianNB') {
            throw new InvalidModelArtifactException('Pipeline artifact tidak didukung.');
        }

        $featureCount = count(self::FEATURE_ORDER);
        $imputer = $data['preprocessing']['imputer']['statistics'] ?? null;
        $power = $data['preprocessing']['power_transformer'] ?? null;
        $classifier = $data['classifier'] ?? null;

        if (! is_array($imputer) || count($imputer) !== $featureCount
            || ! is_array($power)
            || ! $this->isVector($power['lambdas'] ?? null, $featureCount)
            || ! $this->isVector($power['scaler_mean'] ?? null, $featureCount)
            || ! $this->isVector($power['scaler_scale'] ?? null, $featureCount)
            || ! is_array($classifier)) {
            throw new InvalidModelArtifactException('Dimensi parameter artifact tidak valid.');
        }

        $classes = $classifier['classes'] ?? null;
        $priors = $classifier['class_prior'] ?? null;
        $theta = $classifier['theta'] ?? null;
        $variance = $classifier['variance'] ?? null;

        if (! is_array($classes) || count($classes) !== 2
            || ! $this->isVector($priors, 2)
            || ! $this->isMatrix($theta, 2, $featureCount)
            || ! $this->isMatrix($variance, 2, $featureCount)) {
            throw new InvalidModelArtifactException('Dimensi classifier artifact tidak valid.');
        }

        $numericGroups = [
            $imputer,
            $power['lambdas'],
            $power['scaler_mean'],
            $power['scaler_scale'],
            $priors,
            ...$theta,
            ...$variance,
            [$power['lambda_zero_tolerance'] ?? null, $power['lambda_two_tolerance'] ?? null],
        ];

        foreach ($numericGroups as $values) {
            foreach ($values as $value) {
                if (! is_int($value) && ! is_float($value)) {
                    throw new InvalidModelArtifactException('Parameter numerik artifact tidak valid.');
                }

                if (! is_finite((float) $value)) {
                    throw new InvalidModelArtifactException('Parameter numerik artifact tidak valid.');
                }
            }
        }

        foreach ([...$power['scaler_scale'], ...$variance[0], ...$variance[1]] as $value) {
            if ((float) $value <= 0.0) {
                throw new InvalidModelArtifactException('Scale dan variance harus lebih besar dari nol.');
            }
        }

        $priorSum = 0.0;
        foreach ($priors as $prior) {
            if ((float) $prior <= 0.0) {
                throw new InvalidModelArtifactException('Prior kelas artifact tidak valid.');
            }

            $priorSum += (float) $prior;
        }

        if (abs($priorSum - 1.0) > 1.0e-12) {
            throw new InvalidModelArtifactException('Prior kelas artifact tidak valid.');
        }

        $positiveClass = $data['target']['positive_class'] ?? null;
        if (array_search($positiveClass, $classes, true) === false) {
            throw new InvalidModelArtifactException('Kelas positif artifact tidak valid.');
        }

        $threshold = $data['decision']['probability_threshold'] ?? null;
        if ((! is_int($threshold) && ! is_float($threshold))
            || ! is_finite((float) $threshold)
            || $threshold < 0
            || $threshold > 1) {
            throw new InvalidModelArtifactException('Threshold probabilitas tidak valid.');
        }
    }

    private function isVector(mixed $value, int $length): bool
    {
        return is_array($value) && count($value) === $length;
    }

    private function isMatrix(mixed $value, int $rows, int $columns): bool
    {
        if (! is_array($value) || count($value) !== $rows) {
            return false;
        }

        foreach ($value as $row) {
            if (! $this->isVector($row, $columns)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, int|float>  $values
     * @return list<float>
     */
    private function floats(array $values): array
    {
        return array_map(static fn (int|float $value): float => (float) $value, array_values($values));
    }
}
