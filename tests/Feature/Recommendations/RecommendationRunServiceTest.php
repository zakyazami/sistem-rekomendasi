<?php

use App\Contracts\RecommendationEngine;
use App\Domain\Recommendation\Enums\RecommendationItemStatus;
use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Jobs\ProcessRecommendationRun;
use App\Models\Category;
use App\Models\Product;
use App\Models\RecommendationRun;
use App\Models\StockHistory;
use App\Models\StockRecommendation;
use App\Models\User;
use App\Services\Recommendation\RecommendationRunProcessor;
use App\Services\Recommendation\RecommendationRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function recommendationUser(): User
{
    return User::query()->create([
        'name' => 'Admin Rekomendasi',
        'email' => 'recommendation-admin@example.test',
        'password' => 'password',
        'role' => 'admin',
    ]);
}

function recommendationProduct(int $historyCount = 8): Product
{
    $category = Category::query()->create(['name' => 'Kategori Rekomendasi']);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'sku' => 'BRK-RECOMMENDATION',
        'name' => 'Produk Rekomendasi',
        'moving_type' => 'MEDIUM',
        'minimum_stock' => 10,
        'on_order_quantity' => 0,
        'is_active' => true,
    ]);

    $stock = 50;
    foreach (range(1, $historyCount) as $day) {
        $outgoing = $day === $historyCount ? 3 : ($day % 4) + 1;
        StockHistory::query()->create([
            'product_id' => $product->id,
            'date' => sprintf('2025-08-%02d', $day),
            'initial_stock' => $stock,
            'incoming_stock' => 0,
            'outgoing_stock' => $outgoing,
            'final_stock' => $stock - $outgoing,
        ]);
        $stock -= $outgoing;
    }

    return $product;
}

it('creates and completes a synchronous recommendation run with audited results', function () {
    $user = recommendationUser();
    $product = recommendationProduct();

    $run = app(RecommendationRunService::class)->start(
        triggeredBy: $user,
        queue: false,
        idempotencyKey: 'sync-run-001',
    )->refresh();

    $result = StockRecommendation::query()->sole();

    expect($run->status)->toBe(RecommendationRunStatus::Completed)
        ->and($run->total_products)->toBe(1)
        ->and($run->processed_products)->toBe(1)
        ->and($result->product_id)->toBe($product->id)
        ->and($result->model_probability_positive)->not->toBeNull()
        ->and($result->feature_payload)->toHaveKeys(['raw', 'transformed'])
        ->and($result->model_threshold)->toBe(0.99);
});

it('returns the same run for the same idempotency key', function () {
    $user = recommendationUser();
    recommendationProduct();
    $service = app(RecommendationRunService::class);

    $first = $service->start($user, false, 'same-run-key');
    $second = $service->start($user, false, 'same-run-key');

    expect($second->is($first))->toBeTrue()
        ->and(RecommendationRun::query()->count())->toBe(1)
        ->and(StockRecommendation::query()->count())->toBe(1);
});

it('dispatches a unique queued job while leaving the run pending', function () {
    Queue::fake();
    $user = recommendationUser();
    recommendationProduct();

    $run = app(RecommendationRunService::class)->start($user, true, 'queued-run-001');

    expect($run->status)->toBe(RecommendationRunStatus::Pending);
    Queue::assertPushed(ProcessRecommendationRun::class, fn (ProcessRecommendationRun $job): bool => $job->runId === $run->id);
});

it('persists data tidak cukup without an invented probability', function () {
    $user = recommendationUser();
    recommendationProduct(7);

    $run = app(RecommendationRunService::class)->start($user, false, 'insufficient-run')->refresh();
    $result = StockRecommendation::query()->sole();

    expect($run->status)->toBe(RecommendationRunStatus::Completed)
        ->and($run->insufficient_products)->toBe(1)
        ->and($result->item_status)->toBe(RecommendationItemStatus::InsufficientData)
        ->and($result->final_recommendation)->toBe('Data Tidak Cukup')
        ->and($result->model_probability_positive)->toBeNull();
});

it('rolls back results and marks the run failed when processing throws', function () {
    Queue::fake();
    $user = recommendationUser();
    recommendationProduct();
    $run = app(RecommendationRunService::class)->start($user, true, 'failing-run');
    $engine = Mockery::mock(RecommendationEngine::class);
    $engine->shouldReceive('recommendHistories')->once()->andThrow(new RuntimeException('simulated failure'));
    app()->instance(RecommendationEngine::class, $engine);
    $processor = app(RecommendationRunProcessor::class);

    expect(fn () => $processor->process($run))->toThrow(RuntimeException::class, 'simulated failure');

    expect($run->refresh()->status)->toBe(RecommendationRunStatus::Failed)
        ->and($run->error_summary)->toBe('Proses rekomendasi gagal. Periksa log aplikasi.')
        ->and(StockRecommendation::query()->count())->toBe(0);
});
