<?php

namespace Database\Seeders;

use App\Models\InventorySetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
        ]);

        InventorySetting::query()->updateOrCreate(
            ['scope_key' => 'global'],
            [
                'product_id' => null,
                'lead_time_days' => 3,
                'review_period_days' => 7,
                'service_level' => 0.95,
                'prediction_horizon_days' => 1,
            ],
        );
    }
}
