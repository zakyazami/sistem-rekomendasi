<?php

namespace Database\Factories;

use App\Domain\Recommendation\Enums\RecommendationItemStatus;
use App\Domain\Recommendation\Enums\RecommendationLabel;
use App\Models\Product;
use App\Models\RecommendationRun;
use App\Models\StockRecommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockRecommendation> */
class StockRecommendationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recommendation_run_id' => RecommendationRun::factory(),
            'product_id' => Product::factory(),
            'item_status' => RecommendationItemStatus::Success,
            'data_date' => fake()->date(),
            'history_count' => 8,
            'on_order_quantity' => 0,
            'model_threshold' => 0.99,
            'model_classification' => RecommendationLabel::NoOrder->value,
            'inventory_trigger' => false,
            'final_recommendation' => RecommendationLabel::NoOrder->value,
            'recommended_quantity' => 0,
            'reason_codes' => ['no_order_required'],
            'warnings' => [],
            'feature_payload' => null,
        ];
    }
}
