<?php

namespace App\Services\MachineLearning;

use App\Domain\Recommendation\Data\ModelArtifact;
use InvalidArgumentException;

final class MedianImputer
{
    /**
     * @param  array<string, int|float|string|null>  $features
     * @return list<float>
     */
    public function transform(array $features, ModelArtifact $artifact): array
    {
        $values = [];

        foreach ($artifact->featureOrder as $index => $featureName) {
            $value = $features[$featureName] ?? null;

            if ($value === null || $this->isNonFinite($value)) {
                $values[] = $artifact->imputerStatistics[$index];

                continue;
            }

            if (! is_int($value) && ! is_float($value) && ! is_numeric($value)) {
                throw new InvalidArgumentException("Fitur {$featureName} harus berupa angka atau null.");
            }

            $values[] = (float) $value;
        }

        return $values;
    }

    private function isNonFinite(mixed $value): bool
    {
        return (is_int($value) || is_float($value) || is_numeric($value))
            && ! is_finite((float) $value);
    }
}
