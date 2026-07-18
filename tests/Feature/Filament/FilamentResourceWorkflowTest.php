<?php

use App\Domain\Users\UserRole;
use App\Filament\Resources\InventorySettings\InventorySettingResource;
use App\Filament\Resources\ModelVersions\ModelVersionResource;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\RecommendationRuns\RecommendationRunResource;
use App\Filament\Resources\StockHistories\Pages\CreateStockHistory;
use App\Filament\Resources\StockHistories\Pages\ListStockHistories;
use App\Filament\Resources\StockImports\StockImportResource;
use App\Filament\Resources\StockRecommendations\StockRecommendationResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Widgets\InventoryStatsWidget;
use App\Filament\Widgets\ModelHealthOverviewWidget;
use App\Filament\Widgets\RecommendationProcessStatusWidget;
use App\Filament\Widgets\TopPriorityRecommendations;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function filamentAdmin(string $email = 'filament-admin@example.test'): User
{
    return User::query()->create([
        'name' => 'Admin Filament',
        'email' => $email,
        'password' => 'password-lama',
        'role' => UserRole::Admin,
    ]);
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('registers the operational inventory and recommendation resources without the framework promotion widget', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getResources())->toContain(
        InventorySettingResource::class,
        ModelVersionResource::class,
        RecommendationRunResource::class,
        StockRecommendationResource::class,
        StockImportResource::class,
    )->and($panel->getWidgets())->toContain(
        InventoryStatsWidget::class,
        RecommendationProcessStatusWidget::class,
        ModelHealthOverviewWidget::class,
        TopPriorityRecommendations::class,
    )->not->toContain(FilamentInfoWidget::class);
});

it('creates stock history through the tested server-side stock service', function () {
    $admin = filamentAdmin();
    $category = Category::query()->create(['name' => 'Filament Stok']);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'sku' => 'BRK-FILAMENT-STOCK',
        'name' => 'Produk Filament',
        'moving_type' => 'MEDIUM',
        'minimum_stock' => 10,
        'on_order_quantity' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($admin);
    StockHistory::query()->create([
        'product_id' => $product->id,
        'date' => '2025-07-31',
        'initial_stock' => 10,
        'incoming_stock' => 0,
        'outgoing_stock' => 2,
        'final_stock' => 8,
    ]);

    Livewire::test(CreateStockHistory::class)
        ->fillForm([
            'date' => '2025-08-01',
            'product_id' => $product->id,
            'initial_stock' => 999,
            'incoming_stock' => 4,
            'outgoing_stock' => 3,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('stock_histories', [
        'product_id' => $product->id,
        'initial_stock' => 8,
        'incoming_stock' => 4,
        'outgoing_stock' => 3,
        'final_stock' => 9,
    ]);
});

it('captures the stable sku and on-order inventory in the Filament product form', function () {
    $admin = filamentAdmin('product-filament-admin@example.test');
    $category = Category::query()->create(['name' => 'Produk Form']);
    $this->actingAs($admin);

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'category_id' => $category->id,
            'sku' => 'BRK-PRODUCT-FORM',
            'name' => 'Produk dari Form',
            'moving_type' => 'FAST',
            'minimum_stock' => 8,
            'on_order_quantity' => 12,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('products', [
        'sku' => 'BRK-PRODUCT-FORM',
        'on_order_quantity' => 12,
    ]);
});

it('offers product and date filters for daily stock operations', function () {
    $this->actingAs(filamentAdmin('stock-filter-admin@example.test'));

    Livewire::test(ListStockHistories::class)
        ->assertTableFilterExists('product_id')
        ->assertTableFilterExists('date_range');
});

it('keeps the password hash when an administrator leaves the Filament edit password empty', function () {
    $admin = filamentAdmin('edit-filament-admin@example.test');
    $originalHash = $admin->password;
    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->fillForm([
            'name' => 'Admin Diperbarui',
            'email' => $admin->email,
            'role' => UserRole::Admin->value,
            'password' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();

    expect($admin->name)->toBe('Admin Diperbarui')
        ->and($admin->password)->toBe($originalHash)
        ->and(Hash::check('password-lama', $admin->password))->toBeTrue();
});

it('routes the Filament delete action through the self and last-admin guard', function () {
    $admin = filamentAdmin('protected-filament-admin@example.test');
    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->callAction('delete')
        ->assertActionHalted();

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});
