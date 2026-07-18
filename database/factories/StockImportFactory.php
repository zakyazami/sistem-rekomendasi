<?php

namespace Database\Factories;

use App\Models\StockImport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StockImport> */
class StockImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'user_id' => null,
            'original_name' => 'dataset.csv',
            'stored_path' => 'imports/'.Str::uuid().'.csv',
            'checksum' => hash('sha256', fake()->unique()->uuid()),
            'status' => 'completed',
            'mode' => 'commit',
            'total_rows' => 0,
            'valid_rows' => 0,
            'inserted_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'validation_summary' => [],
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }
}
