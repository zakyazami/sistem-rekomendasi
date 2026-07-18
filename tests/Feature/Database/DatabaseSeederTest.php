<?php

use App\Domain\Users\UserRole;
use App\Models\Category;
use App\Models\InventorySetting;
use App\Models\ModelVersion;
use App\Models\Product;
use App\Models\RecommendationRun;
use App\Models\StockHistory;
use App\Models\StockImport;
use App\Models\StockRecommendation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the complete reference categories, an administrator, and global inventory defaults idempotently', function () {
    $this->seed();
    $this->seed();

    expect(Category::query()->count())->toBe(21)
        ->and(Category::query()->pluck('name')->all())->toContain('Biskuit', 'Protein', 'Tissue')
        ->and(User::query()->where('role', UserRole::Admin->value)->count())->toBeGreaterThanOrEqual(1);

    $global = InventorySetting::query()->where('scope_key', 'global')->sole();

    expect($global->lead_time_days)->toBe(3)
        ->and($global->review_period_days)->toBe(7)
        ->and($global->service_level)->toBe(0.95)
        ->and($global->prediction_horizon_days)->toBe(1);
});

it('provides valid factories for the inventory aggregate', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $history = StockHistory::factory()->for($product)->create();

    expect($category->name)->not->toBeEmpty()
        ->and($product->sku)->toStartWith('BRK-')
        ->and($history->final_stock)->toBe(
            $history->initial_stock + $history->incoming_stock - $history->outgoing_stock,
        );
});

it('enforces a non-null stable sku at the database boundary', function () {
    $category = Category::factory()->create();

    expect(fn () => Product::query()->create([
        'category_id' => $category->id,
        'name' => 'Produk Tanpa SKU',
        'moving_type' => 'MEDIUM',
        'minimum_stock' => 10,
        'on_order_quantity' => 0,
        'is_active' => true,
    ]))->toThrow(QueryException::class);
});

it('provides valid factories for recommendation and import persistence', function () {
    $setting = InventorySetting::factory()->create();
    $model = ModelVersion::factory()->create();
    $run = RecommendationRun::factory()->for($model)->create();
    $recommendation = StockRecommendation::factory()->for($run, 'run')->create();
    $import = StockImport::factory()->create();

    expect($setting->service_level)->toBeGreaterThan(0.0)
        ->and($run->modelVersion->is($model))->toBeTrue()
        ->and($recommendation->run->is($run))->toBeTrue()
        ->and($import->public_id)->not->toBeEmpty();
});
