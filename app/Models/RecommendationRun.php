<?php

namespace App\Models;

use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecommendationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'idempotency_key',
        'status',
        'triggered_by',
        'model_version_id',
        'retry_of_id',
        'started_at',
        'finished_at',
        'data_date',
        'total_products',
        'processed_products',
        'succeeded_products',
        'failed_products',
        'insufficient_products',
        'parameter_snapshot',
        'error_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecommendationRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'data_date' => 'date',
            'parameter_snapshot' => 'array',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class);
    }

    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(StockRecommendation::class);
    }
}
