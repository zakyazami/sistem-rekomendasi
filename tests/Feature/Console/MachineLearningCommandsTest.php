<?php

use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\RecommendationRun;
use App\Models\StockHistory;
use App\Services\MachineLearning\ModelArtifactLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function productForRecommendationCommand(): Product
{
    $category = Category::query()->create(['name' => 'Command']);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'sku' => 'BRK-COMMAND',
        'name' => 'Produk Command',
        'moving_type' => 'MEDIUM',
        'minimum_stock' => 10,
        'on_order_quantity' => 0,
        'is_active' => true,
    ]);
    $stock = 30;

    foreach (range(1, 8) as $day) {
        StockHistory::query()->create([
            'product_id' => $product->id,
            'date' => sprintf('2025-08-%02d', $day),
            'initial_stock' => $stock,
            'incoming_stock' => 0,
            'outgoing_stock' => 2,
            'final_stock' => $stock - 2,
        ]);
        $stock -= 2;
    }

    return $product;
}

it('prints validated local model information', function () {
    $this->artisan('ml:model-info')
        ->expectsOutputToContain('naive_bayes_rekomendasi_stok_toko_barokah')
        ->expectsOutputToContain('1.0.0')
        ->expectsOutputToContain('520f43985da4')
        ->expectsOutputToContain('9')
        ->assertExitCode(0);
});

it('returns a nonzero model info exit code for an invalid artifact', function () {
    config()->set('ml.artifact_path', base_path('missing-model.json'));
    app()->forgetInstance(ModelArtifactLoader::class);

    $this->artisan('ml:model-info')
        ->expectsOutputToContain('Artifact model tidak ditemukan.')
        ->assertExitCode(1);
});

it('verifies all model and recommendation parity fixtures', function () {
    $this->artisan('ml:verify-parity')
        ->expectsOutputToContain('100 vector model valid')
        ->expectsOutputToContain('78 rekomendasi valid')
        ->assertExitCode(0);
});

it('runs recommendations synchronously from artisan', function () {
    productForRecommendationCommand();

    $this->artisan('recommendations:run --sync')
        ->expectsOutputToContain('selesai')
        ->assertExitCode(0);

    expect(RecommendationRun::query()->sole()->status)
        ->toBe(RecommendationRunStatus::Completed);
});
