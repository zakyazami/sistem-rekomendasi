<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('preserves the existing category product and stock relationships', function () {
    $category = Category::query()->create([
        'name' => 'Minuman',
        'description' => 'Produk minuman',
    ]);

    $product = Product::query()->create([
        'category_id' => $category->id,
        'sku' => 'BRK-AQUA-600',
        'name' => 'Aqua 600ml',
        'moving_type' => 'FAST',
        'minimum_stock' => 10,
        'is_active' => true,
    ]);

    $history = StockHistory::query()->create([
        'product_id' => $product->id,
        'date' => '2025-08-31',
        'initial_stock' => 20,
        'incoming_stock' => 0,
        'outgoing_stock' => 4,
        'final_stock' => 16,
    ]);

    expect($category->products()->first()->is($product))->toBeTrue()
        ->and($product->category->is($category))->toBeTrue()
        ->and($history->product->is($product))->toBeTrue()
        ->and($history->date->toDateString())->toBe('2025-08-31')
        ->and($product->is_active)->toBeTrue();
});

it('preserves automatic password hashing on users', function () {
    $user = User::query()->create([
        'name' => 'Admin Toko',
        'email' => 'admin@example.test',
        'password' => 'rahasia-kuat',
    ]);

    expect($user->password)->not->toBe('rahasia-kuat')
        ->and(Hash::check('rahasia-kuat', $user->password))->toBeTrue();
});
