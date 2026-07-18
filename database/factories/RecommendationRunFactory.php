<?php

namespace Database\Factories;

use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Models\ModelVersion;
use App\Models\RecommendationRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RecommendationRun> */
class RecommendationRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'status' => RecommendationRunStatus::Pending,
            'triggered_by' => null,
            'model_version_id' => ModelVersion::factory(),
            'retry_of_id' => null,
            'total_products' => 0,
            'processed_products' => 0,
            'succeeded_products' => 0,
            'failed_products' => 0,
            'insufficient_products' => 0,
            'parameter_snapshot' => [
                'lead_time_days' => 3,
                'review_period_days' => 7,
                'service_level' => 0.95,
                'prediction_horizon_days' => 1,
            ],
        ];
    }
}
