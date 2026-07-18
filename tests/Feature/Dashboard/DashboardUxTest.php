<?php

use App\Domain\Recommendation\Enums\RecommendationItemStatus;
use App\Domain\Recommendation\Enums\RecommendationLabel;
use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Domain\Users\UserRole;
use App\Filament\Pages\Dashboard;
use App\Models\Category;
use App\Models\ModelVersion;
use App\Models\Product;
use App\Models\RecommendationRun;
use App\Models\StockHistory;
use App\Models\StockRecommendation;
use App\Models\User;
use App\Services\Dashboard\DashboardOverviewService;
use App\Services\Dashboard\LatestRecommendationQuery;
use App\Services\MachineLearning\ModelArtifactLoader;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2025-09-01 10:00:00');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function dashboardUxUser(UserRole $role, string $email): User
{
    return User::factory()->create([
        'name' => $role === UserRole::Admin ? 'Admin Dashboard' : 'Pemilik Dashboard',
        'email' => $email,
        'role' => $role,
    ]);
}

function dashboardUxProduct(bool $withHistory = true): Product
{
    $category = Category::query()->firstOrCreate(['name' => 'Dashboard UX']);
    $product = Product::factory()->for($category)->create([
        'sku' => 'BRK-DASHBOARD-UX-'.Product::query()->count(),
        'name' => 'Produk Dashboard UX '.Product::query()->count(),
        'is_active' => true,
    ]);

    if ($withHistory) {
        StockHistory::factory()->for($product)->create([
            'date' => '2025-08-31',
            'initial_stock' => 20,
            'incoming_stock' => 0,
            'outgoing_stock' => 4,
            'final_stock' => 16,
        ]);
    }

    return $product;
}

function dashboardUxRun(
    RecommendationRunStatus $status,
    ?Product $product = null,
    int $quantity = 12,
    float $probability = 0.995,
    bool $inventoryTrigger = true,
): RecommendationRun {
    $model = ModelVersion::factory()->create();
    $run = RecommendationRun::factory()->for($model)->create([
        'status' => $status,
        'started_at' => '2025-09-01 09:58:30',
        'finished_at' => in_array($status, [RecommendationRunStatus::Completed, RecommendationRunStatus::Failed], true)
            ? '2025-09-01 10:00:00'
            : null,
        'data_date' => '2025-08-31',
        'total_products' => $product ? 1 : 0,
        'processed_products' => $product ? 1 : 0,
        'succeeded_products' => $status === RecommendationRunStatus::Completed && $product ? 1 : 0,
        'failed_products' => $status === RecommendationRunStatus::Failed ? 1 : 0,
        'error_summary' => $status === RecommendationRunStatus::Failed
            ? 'Proses rekomendasi gagal. Periksa log aplikasi.'
            : null,
    ]);

    if ($status === RecommendationRunStatus::Completed && $product) {
        StockRecommendation::factory()->for($run, 'run')->for($product)->create([
            'item_status' => RecommendationItemStatus::Success,
            'data_date' => '2025-08-31',
            'current_stock' => 16,
            'projected_inventory' => 12,
            'reorder_point' => 18,
            'model_probability_positive' => $probability,
            'model_classification' => RecommendationLabel::NeedsOrder->value,
            'inventory_trigger' => $inventoryTrigger,
            'final_recommendation' => RecommendationLabel::NeedsOrder->value,
            'recommended_quantity' => $quantity,
            'reason_codes' => $inventoryTrigger
                ? ['model_prediction_positive', 'inventory_below_reorder_point']
                : ['model_prediction_positive'],
        ]);
    }

    return $run;
}

it('formats operational and model health values for Indonesian decision making', function () {
    $product = dashboardUxProduct();
    dashboardUxRun(RecommendationRunStatus::Completed, $product, quantity: 1234);

    $overview = app(DashboardOverviewService::class)->get();

    expect($overview->activeProductsLabel)->toBe('1')
        ->and($overview->latestDataDateLabel)->toBe('31 Agustus 2025')
        ->and($overview->needsOrderLabel)->toBe('1')
        ->and($overview->totalRecommendedQuantityLabel)->toBe('1.234')
        ->and($overview->artifactValid)->toBeTrue()
        ->and($overview->artifactStatusLabel)->toBe('Artifact Valid')
        ->and($overview->modelName)->toBe('Naive Bayes Rekomendasi Stok')
        ->and($overview->modelVersionLabel)->toBe('v1.0.0')
        ->and($overview->thresholdLabel)->toBe('99,00%')
        ->and($overview->trainedAtLabel)->toContain('18 Juli 2026')->not->toContain('T16:15')
        ->and($overview->mainMetrics['accuracy']['value'])->toBe('96,59%')
        ->and($overview->mainMetrics['precision']['value'])->toBe('58,53%')
        ->and($overview->mainMetrics['recall']['value'])->toBe('71,35%')
        ->and($overview->mainMetrics['f1']['value'])->toBe('64,30%')
        ->and($overview->advancedMetrics)->toHaveKeys(['balanced_accuracy', 'pr_auc', 'roc_auc'])
        ->and($overview->runStatusLabel)->toBe('Berhasil')
        ->and($overview->runDurationLabel)->toBe('1 menit 30 detik');
});

it('returns actionable empty data no-run and failed-run states', function () {
    $empty = app(DashboardOverviewService::class)->get();
    expect($empty->nextAction)->toBe('import')
        ->and($empty->emptyStateHeading)->toBe('Belum ada data persediaan.');

    dashboardUxProduct();
    $withoutRun = app(DashboardOverviewService::class)->get();
    expect($withoutRun->nextAction)->toBe('run')
        ->and($withoutRun->needsOrderLabel)->toBe('Belum dihitung');

    dashboardUxRun(RecommendationRunStatus::Failed);
    $failed = app(DashboardOverviewService::class)->get();
    expect($failed->runStatusLabel)->toBe('Gagal')
        ->and($failed->nextAction)->toBe('retry')
        ->and($failed->runError)->toBe('Proses rekomendasi gagal. Periksa log aplikasi.');
});

it('keeps the dashboard renderable with an invalid artifact and exposes a safe state', function () {
    config()->set('ml.artifact_path', base_path('resources/ml/missing-dashboard-artifact.json'));
    app()->forgetInstance(ModelArtifactLoader::class);

    $overview = app(DashboardOverviewService::class)->get();

    expect($overview->artifactValid)->toBeFalse()
        ->and($overview->artifactStatusLabel)->toBe('Artifact Tidak Valid')
        ->and($overview->modelName)->toBe('Model tidak tersedia');
});

it('reads priority only from the latest successful run with eager-loaded product relations', function () {
    $olderProduct = dashboardUxProduct();
    $newerProduct = dashboardUxProduct();
    $failedProduct = dashboardUxProduct();
    $older = dashboardUxRun(RecommendationRunStatus::Completed, $olderProduct, quantity: 99);
    $older->forceFill(['finished_at' => '2025-08-31 08:00:00'])->save();
    $latest = dashboardUxRun(RecommendationRunStatus::Completed, $newerProduct, quantity: 25, probability: 0.997);
    dashboardUxRun(RecommendationRunStatus::Failed, $failedProduct);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $rows = app(LatestRecommendationQuery::class)->topPriority(10);
    $queryCount = count(DB::getQueryLog());

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->recommendation_run_id)->toBe($latest->id)
        ->and($rows->first()->relationLoaded('product'))->toBeTrue()
        ->and($rows->first()->product->relationLoaded('category'))->toBeTrue()
        ->and($queryCount)->toBeLessThanOrEqual(4)
        ->and(app(LatestRecommendationQuery::class)->triggerLabel($rows->first()))->toBe('Keduanya');
});

it('renders the custom dashboard and applies policies to operational header actions', function () {
    $admin = dashboardUxUser(UserRole::Admin, 'admin-dashboard-ux@example.test');
    $this->actingAs($admin);

    Livewire::test(Dashboard::class)
        ->assertSee('Dashboard Persediaan')
        ->assertSee('Pantau kondisi stok dan rekomendasi pemesanan terbaru.')
        ->assertActionVisible('import_data')
        ->assertActionVisible('run_recommendations')
        ->assertActionDisabled('run_recommendations')
        ->assertActionVisible('view_results');

    $owner = dashboardUxUser(UserRole::Owner, 'owner-dashboard-ux@example.test');
    $this->actingAs($owner);

    Livewire::test(Dashboard::class)
        ->assertActionHidden('import_data')
        ->assertActionVisible('run_recommendations');
});
