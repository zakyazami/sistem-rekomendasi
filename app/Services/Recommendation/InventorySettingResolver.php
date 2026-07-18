<?php

namespace App\Services\Recommendation;

use App\Domain\Recommendation\Data\InventoryParameters;
use App\Models\InventorySetting;
use App\Models\Product;

final class InventorySettingResolver
{
    public function forProduct(Product $product): InventoryParameters
    {
        $setting = InventorySetting::query()->where('product_id', $product->id)->first()
            ?? InventorySetting::query()->where('scope_key', 'global')->first();

        return new InventoryParameters(
            leadTimeDays: (int) ($setting?->lead_time_days ?? 3),
            reviewPeriodDays: (int) ($setting?->review_period_days ?? 7),
            serviceLevel: (float) ($setting?->service_level ?? 0.95),
            horizonDays: (int) ($setting?->prediction_horizon_days ?? 1),
            onOrderQuantity: (int) $product->on_order_quantity,
        );
    }
}
