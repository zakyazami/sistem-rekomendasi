<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\StockHistory;
use App\Services\Inventory\InventoryFeatureEngineeringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function productForFeatureEngineering(): Product
{
    $category = Category::query()->create(['name' => 'Pengujian']);

    return Product::query()->create([
        'category_id' => $category->id,
        'sku' => 'BRK-FEATURE-TEST',
        'name' => 'Produk Uji',
        'moving_type' => 'MEDIUM',
        'minimum_stock' => 10,
        'is_active' => true,
    ]);
}

function addStockHistory(Product $product, string $date, int $outgoing, int $finalStock = 50): StockHistory
{
    return StockHistory::query()->create([
        'product_id' => $product->id,
        'date' => $date,
        'initial_stock' => $finalStock + $outgoing,
        'incoming_stock' => 0,
        'outgoing_stock' => $outgoing,
        'final_stock' => $finalStock,
    ]);
}

it('excludes the current row and uses population deviation for the previous seven records', function () {
    $product = productForFeatureEngineering();

    foreach (range(1, 7) as $day) {
        addStockHistory($product, "2025-08-0{$day}", $day);
    }

    addStockHistory($product, '2025-08-31', 100, 12);

    $result = (new InventoryFeatureEngineeringService)->build(
        $product->stockHistories()->get(),
        1,
    );

    expect($result)->not->toBeNull()
        ->and($result->values['barang_keluar'])->toBe(100.0)
        ->and($result->values['rata_penjualan_7'])->toBe(4.0)
        ->and($result->values['std_penjualan_7'])->toEqualWithDelta(2.0, 1.0e-12)
        ->and($result->values['rata_penjualan_30'])->toBe(4.0)
        ->and($result->values['std_penjualan_30'])->toEqualWithDelta(2.0, 1.0e-12);
});

it('uses the latest thirty records rather than thirty calendar days after chronological sorting', function () {
    $product = productForFeatureEngineering();
    $start = Carbon::parse('2025-01-01');

    foreach (array_reverse(range(1, 31)) as $sequence) {
        addStockHistory(
            $product,
            $start->copy()->addDays(($sequence - 1) * 2)->toDateString(),
            $sequence,
        );
    }

    addStockHistory($product, '2025-08-31', 999, 33);

    $result = (new InventoryFeatureEngineeringService)->build(
        $product->stockHistories()->get(),
        1,
    );
    $expectedDeviation = sqrt(array_sum(array_map(
        static fn (int $value): float => ($value - 16.5) ** 2,
        range(2, 31),
    )) / 30);

    expect($result)->not->toBeNull()
        ->and($result->historyCount)->toBe(32)
        ->and($result->values['rata_penjualan_30'])->toBe(16.5)
        ->and($result->values['std_penjualan_30'])->toEqualWithDelta($expectedDeviation, 1.0e-12)
        ->and($result->dataDate->toDateString())->toBe('2025-08-31');
});

it('maps sunday to six and emits null coverage when previous sales average is zero', function () {
    $product = productForFeatureEngineering();

    foreach (range(1, 7) as $day) {
        addStockHistory($product, "2025-08-0{$day}", 0);
    }

    addStockHistory($product, '2025-08-31', 0, 45);

    $result = (new InventoryFeatureEngineeringService)->build(
        $product->stockHistories()->get(),
        1,
    );

    expect($result)->not->toBeNull()
        ->and($result->values['hari_dalam_minggu'])->toBe(6.0)
        ->and($result->values['cakupan_stok_hari'])->toBeNull();
});

it('returns no feature vector when fewer than eight rows are available', function () {
    $product = productForFeatureEngineering();

    foreach (range(1, 7) as $day) {
        addStockHistory($product, "2025-08-0{$day}", 1);
    }

    expect((new InventoryFeatureEngineeringService)->build(
        $product->stockHistories()->get(),
        1,
    ))->toBeNull();
});
