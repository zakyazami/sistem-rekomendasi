<?php

namespace App\Contracts;

use App\Domain\Recommendation\Data\InventoryParameters;
use App\Domain\Recommendation\Data\RecommendationResult;
use App\Models\StockHistory;

interface RecommendationEngine
{
    /** @param iterable<int, StockHistory> $histories */
    public function recommendHistories(
        iterable $histories,
        InventoryParameters $parameters,
    ): ?RecommendationResult;
}
