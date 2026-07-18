<?php

namespace App\Domain\Recommendation\Data;

use Carbon\CarbonImmutable;

final readonly class FeatureVector
{
    /** @param array<string, float|null> $values */
    public function __construct(
        public array $values,
        public CarbonImmutable $dataDate,
        public int $historyCount,
        public float $currentStock,
        public float $currentOutgoingStock,
    ) {}
}
