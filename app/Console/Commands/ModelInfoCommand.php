<?php

namespace App\Console\Commands;

use App\Exceptions\InvalidModelArtifactException;
use App\Services\MachineLearning\ModelArtifactLoader;
use Illuminate\Console\Command;

class ModelInfoCommand extends Command
{
    protected $signature = 'ml:model-info';

    protected $description = 'Validasi dan tampilkan metadata artifact model lokal';

    public function handle(ModelArtifactLoader $loader): int
    {
        try {
            $artifact = $loader->load();
        } catch (InvalidModelArtifactException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Properti', 'Nilai'], [
            ['Model', $artifact->modelName],
            ['Versi', $artifact->modelVersion],
            ['Schema', $artifact->schemaVersion],
            ['Checksum', substr($artifact->fileChecksum, 0, 12)],
            ['Jumlah fitur', (string) count($artifact->featureOrder)],
            ['Threshold', (string) $artifact->threshold],
            ['Dilatih', (string) ($artifact->trainingMetadata['trained_at'] ?? '-')],
        ]);

        return self::SUCCESS;
    }
}
