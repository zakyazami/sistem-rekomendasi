<?php

namespace App\Services\Recommendation;

use App\Domain\Recommendation\Data\ModelArtifact;
use App\Models\ModelVersion;
use Illuminate\Support\Facades\DB;

final class ModelVersionRegistry
{
    public function activate(ModelArtifact $artifact): ModelVersion
    {
        return DB::transaction(function () use ($artifact): ModelVersion {
            ModelVersion::query()->where('is_active', true)->update(['is_active' => false]);

            $version = ModelVersion::query()->firstOrCreate(
                ['artifact_file_checksum' => $artifact->fileChecksum],
                [
                    'model_name' => $artifact->modelName,
                    'model_version' => $artifact->modelVersion,
                    'schema_version' => $artifact->schemaVersion,
                    'artifact_payload_checksum' => $artifact->payloadChecksum,
                    'feature_order' => $artifact->featureOrder,
                    'threshold' => $artifact->threshold,
                    'metrics' => $artifact->metrics,
                    'training_metadata' => $artifact->trainingMetadata,
                    'manifest_snapshot' => [
                        'artifact_type' => $artifact->artifactType,
                        'inventory_defaults' => $artifact->inventoryDefaults,
                    ],
                ],
            );

            if (! $version->is_active) {
                $version->forceFill(['is_active' => true])->save();
            }

            return $version;
        });
    }
}
