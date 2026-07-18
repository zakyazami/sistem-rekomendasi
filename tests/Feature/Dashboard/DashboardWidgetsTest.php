<?php

use App\Domain\Recommendation\Enums\RecommendationItemStatus;
use App\Domain\Recommendation\Enums\RecommendationLabel;
use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Domain\Users\UserRole;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\StockHistories\StockHistoryResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\InventoryStatsWidget;
use App\Filament\Widgets\ModelHealthOverviewWidget;
use App\Filament\Widgets\RecommendationProcessStatusWidget;
use App\Filament\Widgets\TopPriorityRecommendations;
use App\Models\Category;
use App\Models\ModelVersion;
use App\Models\Product;
use App\Models\RecommendationRun;
use App\Models\StockRecommendation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function widgetPriorityProduct(): Product
{
    $category = Category::query()->create(['name' => 'Prioritas Widget']);

    return Product::factory()->for($category)->create([
        'sku' => 'BRK-WIDGET-PRIORITY',
        'name' => 'Produk Prioritas Widget',
    ]);
}

function widgetPriorityRun(Product $product): RecommendationRun
{
    $run = RecommendationRun::factory()->for(ModelVersion::factory())->create([
        'status' => RecommendationRunStatus::Completed,
        'started_at' => '2025-09-01 09:59:00',
        'finished_at' => '2025-09-01 10:00:00',
        'data_date' => '2025-08-31',
        'total_products' => 1,
        'processed_products' => 1,
        'succeeded_products' => 1,
    ]);
    StockRecommendation::factory()->for($run, 'run')->for($product)->create([
        'item_status' => RecommendationItemStatus::Success,
        'model_probability_positive' => 0.997,
        'model_classification' => RecommendationLabel::NeedsOrder->value,
        'inventory_trigger' => true,
        'final_recommendation' => RecommendationLabel::NeedsOrder->value,
        'recommended_quantity' => 1234,
    ]);

    return $run;
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create([
        'email' => 'dashboard-widget@example.test',
        'role' => UserRole::Admin,
    ]));
});

it('renders four concise operational cards and actionable empty data guidance', function () {
    Livewire::test(InventoryStatsWidget::class)
        ->assertSee('Produk Aktif')
        ->assertSee('Perlu Dipesan')
        ->assertSee('Total Unit Disarankan')
        ->assertSee('Kesegaran Data')
        ->assertDontSee('Data Tidak Cukup')
        ->assertDontSee('Proses Terakhir')
        ->assertDontSee('recommendation run');

    Livewire::test(RecommendationProcessStatusWidget::class)
        ->assertSee('Belum ada data persediaan.')
        ->assertSee('Import Data');
});

it('renders compact localized model health without raw decimals or iso timestamps', function () {
    Livewire::test(ModelHealthOverviewWidget::class)
        ->assertSee('Artifact Valid')
        ->assertSee('Naive Bayes Rekomendasi Stok')
        ->assertSee('v1.0.0')
        ->assertSee('99,00%')
        ->assertSee('96,59%')
        ->assertSee('58,53%')
        ->assertSee('71,35%')
        ->assertSee('64,30%')
        ->assertSee('Metrik Lanjutan')
        ->assertDontSee('Â')
        ->assertDontSee('0.9659')
        ->assertDontSee('0.9900')
        ->assertDontSee('2026-07-18T16:15');
});

it('formats priority probability quantity and Indonesian trigger labels from the latest completed run', function () {
    $product = widgetPriorityProduct();
    widgetPriorityRun($product);

    Livewire::test(TopPriorityRecommendations::class)
        ->assertSee($product->sku)
        ->assertSee('99,70%')
        ->assertSee('Keduanya')
        ->assertSee('1.234')
        ->assertDontSee('recommendation run');
});

it('uses consistent Indonesian labels in the dashboard navigation', function () {
    expect(UserResource::getNavigationLabel())->toBe('Pengguna')
        ->and(CategoryResource::getNavigationLabel())->toBe('Kategori')
        ->and(ProductResource::getNavigationLabel())->toBe('Produk')
        ->and(StockHistoryResource::getNavigationLabel())->toBe('Histori Stok')
        ->and(StockHistoryResource::getNavigationGroup())->toBe('Transaksi');
});
