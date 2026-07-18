<?php

namespace Database\Seeders;

use App\Domain\Users\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\RecommendationRun;
use App\Models\StockHistory;
use App\Models\StockRecommendation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $startedAt = microtime(true);

        $this->call([
            AdminUserSeeder::class,
            InventorySettingSeeder::class,
        ]);

        if ($this->shouldSeedReferenceData()) {
            $this->call(TokoBarokahReferenceDatasetSeeder::class);
        }

        if ((bool) config('seeding.run_recommendations')) {
            $this->call(InitialRecommendationSeeder::class);
        }

        $firstHistoryDate = StockHistory::query()->min('date');
        $lastHistoryDate = StockHistory::query()->max('date');
        $dateRange = $firstHistoryDate === null || $lastHistoryDate === null
            ? 'belum ada data'
            : sprintf(
                '%s sampai %s',
                CarbonImmutable::parse($firstHistoryDate)->toDateString(),
                CarbonImmutable::parse($lastHistoryDate)->toDateString(),
            );

        $this->command?->newLine();
        $this->command?->info(sprintf(
            'Ringkasan seed: %d peran policy, %d admin, %d kategori, %d produk, %d histori (rentang %s), %d run, %d hasil; %.2f detik.',
            count(UserRole::cases()),
            User::query()->where('role', UserRole::Admin->value)->count(),
            Category::query()->count(),
            Product::query()->count(),
            StockHistory::query()->count(),
            $dateRange,
            RecommendationRun::query()->count(),
            StockRecommendation::query()->count(),
            microtime(true) - $startedAt,
        ));
    }

    private function shouldSeedReferenceData(): bool
    {
        return app()->environment(['local', 'testing'])
            || (bool) config('seeding.reference_data');
    }
}
