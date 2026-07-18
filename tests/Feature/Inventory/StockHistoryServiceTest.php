<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\StockHistory;
use App\Services\Inventory\StockHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function productForStockService(): Product
{
    $category = Category::query()->create(['name' => 'Stok']);

    return Product::query()->create([
        'category_id' => $category->id,
        'sku' => 'BRK-STOCK-SERVICE',
        'name' => 'Produk Stok',
        'moving_type' => 'MEDIUM',
        'minimum_stock' => 10,
        'on_order_quantity' => 0,
        'is_active' => true,
    ]);
}

it('calculates final stock on the server and ignores a submitted final value', function () {
    $history = app(StockHistoryService::class)->create(productForStockService(), [
        'date' => '2025-08-01',
        'initial_stock' => 20,
        'incoming_stock' => 4,
        'outgoing_stock' => 7,
        'final_stock' => 999,
    ]);

    expect($history->initial_stock)->toBe(20)
        ->and($history->final_stock)->toBe(17);
});

it('derives the next initial stock from the previous final stock', function () {
    $product = productForStockService();
    $service = app(StockHistoryService::class);
    $service->create($product, [
        'date' => '2025-08-01',
        'initial_stock' => 20,
        'incoming_stock' => 0,
        'outgoing_stock' => 5,
    ]);

    $next = $service->create($product, [
        'date' => '2025-08-02',
        'initial_stock' => 999,
        'incoming_stock' => 3,
        'outgoing_stock' => 4,
    ]);

    expect($next->initial_stock)->toBe(15)
        ->and($next->final_stock)->toBe(14);
});

it('rejects negative calculated stock without writing a row', function () {
    $product = productForStockService();

    expect(fn () => app(StockHistoryService::class)->create($product, [
        'date' => '2025-08-01',
        'initial_stock' => 3,
        'incoming_stock' => 0,
        'outgoing_stock' => 4,
    ]))->toThrow(ValidationException::class);

    expect(StockHistory::query()->count())->toBe(0);
});

it('rejects duplicate or backdated manual stock rows', function () {
    $product = productForStockService();
    $service = app(StockHistoryService::class);
    $service->create($product, [
        'date' => '2025-08-02',
        'initial_stock' => 10,
        'incoming_stock' => 0,
        'outgoing_stock' => 1,
    ]);

    expect(fn () => $service->create($product, [
        'date' => '2025-08-01',
        'initial_stock' => 9,
        'incoming_stock' => 0,
        'outgoing_stock' => 1,
    ]))->toThrow(ValidationException::class);
});

it('recalculates the latest row on update and protects older continuity', function () {
    $product = productForStockService();
    $service = app(StockHistoryService::class);
    $first = $service->create($product, [
        'date' => '2025-08-01',
        'initial_stock' => 20,
        'incoming_stock' => 0,
        'outgoing_stock' => 5,
    ]);
    $latest = $service->create($product, [
        'date' => '2025-08-02',
        'incoming_stock' => 0,
        'outgoing_stock' => 2,
    ]);

    $updated = $service->update($latest, ['incoming_stock' => 3, 'outgoing_stock' => 4]);

    expect($updated->initial_stock)->toBe(15)
        ->and($updated->final_stock)->toBe(14);

    expect(fn () => $service->update($first, ['outgoing_stock' => 1]))
        ->toThrow(ValidationException::class);
});
