<?php

namespace Database\Factories;

use App\Models\ModelVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ModelVersion> */
class ModelVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'model_name' => 'GaussianNB Toko Barokah',
            'model_version' => 'test-'.fake()->unique()->numerify('####'),
            'schema_version' => '1.0.0',
            'artifact_file_checksum' => hash('sha256', fake()->unique()->uuid()),
            'artifact_payload_checksum' => null,
            'feature_order' => [
                'stok_akhir',
                'barang_keluar',
                'rata_penjualan_7',
                'std_penjualan_7',
                'rata_penjualan_30',
                'std_penjualan_30',
                'cakupan_stok_hari',
                'hari_dalam_minggu',
                'horizon_hari_target',
            ],
            'threshold' => 0.99,
            'metrics' => [],
            'training_metadata' => [],
            'manifest_snapshot' => [],
            'is_active' => false,
        ];
    }
}
