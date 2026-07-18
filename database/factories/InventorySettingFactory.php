<?php

namespace Database\Factories;

use App\Models\InventorySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventorySetting> */
class InventorySettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scope_key' => 'scope:'.fake()->unique()->uuid(),
            'product_id' => null,
            'lead_time_days' => 3,
            'review_period_days' => 7,
            'service_level' => 0.95,
            'prediction_horizon_days' => 1,
            'updated_by' => null,
        ];
    }
}
