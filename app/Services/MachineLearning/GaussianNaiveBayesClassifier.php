<?php

namespace App\Services\MachineLearning;

use App\Domain\Recommendation\Data\ModelArtifact;
use App\Domain\Recommendation\Data\ModelPrediction;
use InvalidArgumentException;

final class GaussianNaiveBayesClassifier
{
    /**
     * @param  list<float>  $features
     * @return list<float>
     */
    public function jointLogLikelihood(array $features, ModelArtifact $artifact): array
    {
        if (count($features) !== count($artifact->featureOrder)) {
            throw new InvalidArgumentException('Jumlah fitur hasil transformasi tidak valid.');
        }

        $likelihoods = [];

        foreach ($artifact->classes as $classIndex => $_class) {
            $value = log($artifact->classPrior[$classIndex]);

            foreach ($features as $featureIndex => $feature) {
                $variance = $artifact->variance[$classIndex][$featureIndex];
                $theta = $artifact->theta[$classIndex][$featureIndex];

                $value += -0.5 * log(2.0 * M_PI * $variance);
                $value += -0.5 * (($feature - $theta) ** 2 / $variance);
            }

            $likelihoods[] = $value;
        }

        return $likelihoods;
    }

    /**
     * @param  list<float>  $jointLogLikelihoods
     * @return list<float>
     */
    public function softmax(array $jointLogLikelihoods): array
    {
        if ($jointLogLikelihoods === []) {
            throw new InvalidArgumentException('Joint log likelihood tidak boleh kosong.');
        }

        $maximum = max($jointLogLikelihoods);
        $shifted = array_map(
            static fn (float $value): float => exp($value - $maximum),
            $jointLogLikelihoods,
        );
        $total = array_sum($shifted);

        if (! is_finite($total) || $total <= 0.0) {
            throw new InvalidArgumentException('Probabilitas model tidak dapat dihitung.');
        }

        return array_map(
            static fn (float $value): float => $value / $total,
            $shifted,
        );
    }

    /** @param list<float> $features */
    public function predict(array $features, ModelArtifact $artifact): ModelPrediction
    {
        $jointLogLikelihoods = $this->jointLogLikelihood($features, $artifact);
        $probabilities = $this->softmax($jointLogLikelihoods);
        $positiveIndex = array_search($artifact->positiveClass, $artifact->classes, true);

        if ($positiveIndex === false) {
            throw new InvalidArgumentException('Kelas positif tidak ditemukan pada classifier.');
        }

        $positiveProbability = $probabilities[$positiveIndex];
        $isPositive = $positiveProbability >= $artifact->threshold;
        $predictedIndex = $isPositive
            ? $positiveIndex
            : $this->negativeClassIndex($artifact, $positiveIndex);
        $predictedClass = $artifact->classes[$predictedIndex];
        $predictedLabel = $artifact->labels[(string) $predictedClass]
            ?? $artifact->labels[$predictedClass]
            ?? (string) $predictedClass;

        return new ModelPrediction(
            transformedFeatures: $features,
            jointLogLikelihoods: $jointLogLikelihoods,
            probabilities: $probabilities,
            positiveProbability: $positiveProbability,
            predictedClass: $predictedClass,
            predictedLabel: $predictedLabel,
            isPositive: $isPositive,
        );
    }

    private function negativeClassIndex(ModelArtifact $artifact, int $positiveIndex): int
    {
        foreach (array_keys($artifact->classes) as $index) {
            if ($index !== $positiveIndex) {
                return $index;
            }
        }

        throw new InvalidArgumentException('Kelas negatif tidak ditemukan pada classifier.');
    }
}
