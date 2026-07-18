<?php

namespace App\Services\MachineLearning;

use App\Domain\Recommendation\Data\ModelArtifact;
use App\Domain\Recommendation\Data\ModelPrediction;

final readonly class NaiveBayesInferenceService
{
    public function __construct(
        private MedianImputer $imputer,
        private YeoJohnsonTransformer $transformer,
        private GaussianNaiveBayesClassifier $classifier,
    ) {}

    /** @param array<string, int|float|string|null> $features */
    public function predict(array $features, ModelArtifact $artifact): ModelPrediction
    {
        $imputed = $this->imputer->transform($features, $artifact);
        $transformed = $this->transformer->transform($imputed, $artifact);

        return $this->classifier->predict($transformed, $artifact);
    }
}
