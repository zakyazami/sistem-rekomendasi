<?php

namespace App\Providers;

use App\Contracts\RecommendationEngine;
use App\Services\Inventory\InventoryRecommendationService;
use App\Services\MachineLearning\ModelArtifactLoader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModelArtifactLoader::class, fn (): ModelArtifactLoader => new ModelArtifactLoader(
            (string) config('ml.artifact_path'),
            (string) config('ml.checksum_path'),
        ));

        $this->app->singleton(RecommendationEngine::class, InventoryRecommendationService::class);
    }
}
