<?php

namespace App\Models;

use App\Domain\Recommendation\Enums\RecommendationItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRecommendation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'item_status' => RecommendationItemStatus::class,
            'data_date' => 'date',
            'current_stock' => 'float',
            'current_outgoing_stock' => 'float',
            'average_sales_7' => 'float',
            'std_sales_7' => 'float',
            'average_sales_30' => 'float',
            'std_sales_30' => 'float',
            'stock_coverage_days' => 'float',
            'inventory_position' => 'float',
            'projected_inventory' => 'float',
            'joint_log_likelihood_0' => 'float',
            'joint_log_likelihood_1' => 'float',
            'model_probability_0' => 'float',
            'model_probability_positive' => 'float',
            'model_threshold' => 'float',
            'inventory_trigger' => 'boolean',
            'reason_codes' => 'array',
            'warnings' => 'array',
            'feature_payload' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(RecommendationRun::class, 'recommendation_run_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
