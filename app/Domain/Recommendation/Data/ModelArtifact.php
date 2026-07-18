<?php

namespace App\Domain\Recommendation\Data;

final readonly class ModelArtifact
{
    /**
     * @param  list<string>  $featureOrder
     * @param  list<float>  $imputerStatistics
     * @param  list<float>  $lambdas
     * @param  list<float>  $scalerMean
     * @param  list<float>  $scalerScale
     * @param  list<int|float|string>  $classes
     * @param  list<float>  $classPrior
     * @param  list<list<float>>  $theta
     * @param  list<list<float>>  $variance
     * @param  array<string, string>  $labels
     * @param  array<string, mixed>  $inventoryDefaults
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $trainingMetadata
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $schemaVersion,
        public string $artifactType,
        public string $modelName,
        public string $modelVersion,
        public string $fileChecksum,
        public ?string $payloadChecksum,
        public array $featureOrder,
        public array $imputerStatistics,
        public array $lambdas,
        public array $scalerMean,
        public array $scalerScale,
        public float $lambdaZeroTolerance,
        public float $lambdaTwoTolerance,
        public array $classes,
        public array $classPrior,
        public array $theta,
        public array $variance,
        public int|float|string $positiveClass,
        public array $labels,
        public float $threshold,
        public array $inventoryDefaults,
        public array $metrics,
        public array $trainingMetadata,
        public array $raw,
    ) {}
}
