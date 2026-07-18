<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_name',
        'model_version',
        'schema_version',
        'artifact_file_checksum',
        'artifact_payload_checksum',
        'feature_order',
        'threshold',
        'metrics',
        'training_metadata',
        'manifest_snapshot',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'feature_order' => 'array',
            'threshold' => 'float',
            'metrics' => 'array',
            'training_metadata' => 'array',
            'manifest_snapshot' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function recommendationRuns(): HasMany
    {
        return $this->hasMany(RecommendationRun::class);
    }
}
