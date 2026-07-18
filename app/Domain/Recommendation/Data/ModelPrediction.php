<?php

namespace App\Domain\Recommendation\Data;

final readonly class ModelPrediction
{
    /**
     * @param  list<float>  $transformedFeatures
     * @param  list<float>  $jointLogLikelihoods
     * @param  list<float>  $probabilities
     */
    public function __construct(
        public array $transformedFeatures,
        public array $jointLogLikelihoods,
        public array $probabilities,
        public float $positiveProbability,
        public int|float|string $predictedClass,
        public string $predictedLabel,
        public bool $isPositive,
    ) {}
}
