<?php

namespace App\Services\MachineLearning;

use App\Domain\Recommendation\Data\ModelArtifact;
use InvalidArgumentException;

final class YeoJohnsonTransformer
{
    /**
     * @param  list<float>  $values
     * @return list<float>
     */
    public function transform(array $values, ModelArtifact $artifact): array
    {
        if (count($values) !== count($artifact->featureOrder)) {
            throw new InvalidArgumentException('Jumlah nilai fitur tidak sesuai dengan artifact.');
        }

        $standardized = [];

        foreach ($values as $index => $value) {
            $transformed = $this->transformValue(
                $value,
                $artifact->lambdas[$index],
                $artifact->lambdaZeroTolerance,
                $artifact->lambdaTwoTolerance,
            );

            $standardized[] = ($transformed - $artifact->scalerMean[$index])
                / $artifact->scalerScale[$index];
        }

        return $standardized;
    }

    public function transformValue(
        float $value,
        float $lambda,
        float $zeroTolerance,
        float $twoTolerance,
    ): float {
        if ($value >= 0.0) {
            if (abs($lambda) <= $zeroTolerance) {
                return log1p($value);
            }

            return (pow($value + 1.0, $lambda) - 1.0) / $lambda;
        }

        if (abs($lambda - 2.0) <= $twoTolerance) {
            return -log1p(-$value);
        }

        return -(pow(1.0 - $value, 2.0 - $lambda) - 1.0) / (2.0 - $lambda);
    }
}
