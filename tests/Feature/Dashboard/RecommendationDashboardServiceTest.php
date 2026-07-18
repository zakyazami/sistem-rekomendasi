<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\StockHistory;
use App\Services\Dashboard\RecommendationDashboardService;
use App\Services\Recommendation\RecommendationRunService;
use App\Services\Reporting\RecommendationExportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dashboardRecommendationProduct(): Product
{
    $category = Category::query()->create(['name' => 'Dashboard']);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'sku' => 'BRK-DASHBOARD',
        'name' => 'Produk Rekomendasi',
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

it('builds transparent business and artifact dashboard metrics from the latest run', function () {
    CarbonImmutable::setTestNow('2025-08-10 10:00:00');
    dashboardRecommendationProduct();
    $run = app(RecommendationRunService::class)
        ->start(null, false, 'dashboard-run');

    $metrics = app(RecommendationDashboardService::class)->metrics();

    expect($metrics['active_products'])->toBe(1)
        ->and($metrics['latest_data_date'])->toBe('2025-08-08')
        ->and($metrics['data_is_stale'])->toBeFalse()
        ->and($metrics['needs_order'])->toBeGreaterThanOrEqual(0)
        ->and($metrics['total_recommended_quantity'])->toBeGreaterThanOrEqual(0)
        ->and($metrics['artifact_valid'])->toBeTrue()
        ->and($metrics['model_version'])->toBe('1.0.0')
        ->and($metrics['threshold'])->toBe(0.99)
        ->and($metrics['model_metrics'])->toHaveKeys([
            'accuracy',
            'balanced_accuracy',
            'precision_perlu_pesan',
            'recall_perlu_pesan',
            'f1_perlu_pesan',
            'pr_auc',
            'roc_auc',
        ]);
});

it('exports recommendation transparency fields in an Indonesian csv report', function () {
    dashboardRecommendationProduct();
    $run = app(RecommendationRunService::class)
        ->start(null, false, 'export-run');

    $csv = app(RecommendationExportService::class)->toCsv($run);

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('SKU,Nama Barang,Kategori')
        ->and($csv)->toContain('Probabilitas Perlu Pesan')
        ->and($csv)->toContain('Klasifikasi Model')
        ->and($csv)->toContain('Pemicu Persediaan')
        ->and($csv)->toContain('Rekomendasi Final')
        ->and($csv)->toContain('Produk Rekomendasi');
});
