<?php

use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Domain\Users\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\RecommendationRun;
use App\Models\StockHistory;
use App\Models\StockRecommendation;
use App\Models\User;
use App\Services\Dashboard\DashboardOverviewService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TokoBarokahReferenceDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function configureSeedAdmin(?string $password, bool $force = false): void
{
    config()->set('seeding.admin', [
        'name' => 'Admin Seeder',
        'email' => 'admin-seeder@example.test',
        'password' => $password,
        'force_password' => $force,
    ]);
}

function temporarySeederCsv(array $lines): string
{
    $path = sys_get_temp_dir().'/barokah-seeder-'.bin2hex(random_bytes(5)).'.csv';
    file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);

    return $path;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/barokah-seeder-*.csv') ?: [] as $file) {
        unlink($file);
    }
});

it('creates one administrator and preserves its password unless force is explicit', function () {
    configureSeedAdmin('rahasia-awal');
    app(AdminUserSeeder::class)->run();
    $admin = User::query()->where('email', 'admin-seeder@example.test')->sole();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and(Hash::check('rahasia-awal', $admin->password))->toBeTrue();

    configureSeedAdmin('rahasia-baru');
    $this->seed(AdminUserSeeder::class);
    expect(User::query()->count())->toBe(1)
        ->and(Hash::check('rahasia-awal', $admin->fresh()->password))->toBeTrue();

    configureSeedAdmin('rahasia-baru', force: true);
    $this->seed(AdminUserSeeder::class);
    expect(Hash::check('rahasia-baru', $admin->fresh()->password))->toBeTrue();
});

it('does not create an insecure administrator in production without a configured password', function () {
    app()->instance('env', 'production');
    configureSeedAdmin(null);

    app(AdminUserSeeder::class)->run();

    expect(User::query()->count())->toBe(0);
});

it('imports the complete canonical dataset idempotently with its exact business boundaries', function () {
    config()->set('seeding.reference_dataset_path', base_path('project_naive_bayes/data/raw/dataset_toko_barokah.csv'));

    $this->seed(TokoBarokahReferenceDatasetSeeder::class);
    $this->seed(TokoBarokahReferenceDatasetSeeder::class);

    expect(Category::query()->count())->toBe(21)
        ->and(Product::query()->count())->toBe(78)
        ->and(StockHistory::query()->count())->toBe(27507)
        ->and(StockHistory::query()->min('date'))->toStartWith('2024-08-01')
        ->and(StockHistory::query()->max('date'))->toStartWith('2025-08-31')
        ->and(Product::query()->where('is_active', false)->count())->toBe(0)
        ->and(Product::query()->whereDoesntHave('category')->count())->toBe(0)
        ->and(Product::query()->whereDoesntHave('stockHistories')->count())->toBe(0);

    Product::query()->with(['stockHistories' => fn ($query) => $query->latest('date')->limit(1)])
        ->each(fn (Product $product) => expect($product->stockHistories)->toHaveCount(1));

    $overview = app(DashboardOverviewService::class)->get();
    expect($overview->activeProducts)->toBe(78)
        ->and($overview->latestDataDateLabel)->toBe('31 Agustus 2025')
        ->and($overview->needsOrderLabel)->toBe('Belum dihitung')
        ->and($overview->nextAction)->toBe('run');
});

it('fails clearly and leaves no partial domain data when the reference csv is missing or invalid', function () {
    config()->set('seeding.reference_dataset_path', base_path('missing-reference-dataset.csv'));
    expect(fn () => $this->seed(TokoBarokahReferenceDatasetSeeder::class))
        ->toThrow(ValidationException::class, 'File CSV tidak ditemukan.');

    $invalid = temporarySeederCsv([
        'tanggal,nama_barang,kategori,stok_awal',
        '2025-08-01,Produk,Kategori,10',
    ]);
    config()->set('seeding.reference_dataset_path', $invalid);
    expect(fn () => $this->seed(TokoBarokahReferenceDatasetSeeder::class))
        ->toThrow(ValidationException::class, 'Header CSV tidak sesuai.');

    expect(Category::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBe(0)
        ->and(StockHistory::query()->count())->toBe(0);
});

it('validates canonical date boundaries before committing any reference rows', function () {
    $path = temporarySeederCsv([
        'tanggal,nama_barang,kategori,stok_awal,barang_masuk,barang_keluar,stok_akhir,label',
        '2025-08-01,Produk Rentang,Kategori Rentang,10,0,2,8,Tidak',
    ]);
    config()->set('seeding.reference_dataset_path', $path);
    config()->set('seeding.expected', [
        'rows' => 1,
        'products' => 1,
        'categories' => 1,
        'start_date' => '2025-08-02',
        'end_date' => '2025-08-02',
    ]);

    expect(fn () => $this->seed(TokoBarokahReferenceDatasetSeeder::class))
        ->toThrow(RuntimeException::class, 'Rentang dataset referensi tidak sesuai');

    expect(Category::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBe(0)
        ->and(StockHistory::query()->count())->toBe(0);
});

it('skips reference data by default in production when the explicit flag is false', function () {
    app()->instance('env', 'production');
    config()->set('seeding.reference_data', false);
    configureSeedAdmin(null);

    app(DatabaseSeeder::class)->setContainer(app())->run();

    expect(Product::query()->count())->toBe(0)
        ->and(StockHistory::query()->count())->toBe(0);
});

it('reports the seeded stock-history date range in the final summary', function () {
    app()->instance('env', 'production');
    config()->set('seeding.reference_data', false);
    config()->set('seeding.run_recommendations', false);
    configureSeedAdmin('admin-summary-password');

    $category = Category::query()->create(['name' => 'Ringkasan Seeder']);
    $product = Product::factory()->for($category)->create(['sku' => 'BRK-SEED-SUMMARY']);
    StockHistory::factory()->for($product)->create([
        'date' => '2025-08-01',
        'initial_stock' => 10,
        'incoming_stock' => 0,
        'outgoing_stock' => 2,
        'final_stock' => 8,
    ]);

    $this->artisan('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--force' => true,
    ])
        ->expectsOutputToContain('rentang 2025-08-01 sampai 2025-08-01')
        ->assertSuccessful();
});

it('runs one synchronous traceable recommendation for the same seeded fingerprint', function () {
    $category = Category::query()->create(['name' => 'Rekomendasi Seeder']);
    $product = Product::factory()->for($category)->create(['sku' => 'BRK-SEED-RUN']);
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

    app()->instance('env', 'production');
    config()->set('seeding.reference_data', false);
    config()->set('seeding.run_recommendations', true);
    configureSeedAdmin(null);
    app(DatabaseSeeder::class)->setContainer(app())->run();
    app(DatabaseSeeder::class)->setContainer(app())->run();

    $run = RecommendationRun::query()->sole();

    expect($run->status)->toBe(RecommendationRunStatus::Completed)
        ->and($run->data_date->toDateString())->toBe('2025-08-08')
        ->and(StockRecommendation::query()->count())->toBe(1);
});

it('keeps seeder classes as orchestration adapters without copied inventory or inference formulas', function () {
    $source = collect([
        base_path('database/seeders/TokoBarokahReferenceDatasetSeeder.php'),
        base_path('database/seeders/InitialRecommendationSeeder.php'),
    ])->map(fn (string $path): string => is_file($path) ? file_get_contents($path) : '')->implode("\n");

    expect($source)
        ->toContain('StockCsvImportService', 'RecommendationRunService')
        ->not->toContain('YeoJohnson', 'GaussianNaiveBayes', 'reorder_point', 'safety_stock');
});
