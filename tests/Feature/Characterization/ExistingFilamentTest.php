<?php

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\StockHistories\StockHistoryResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Facades\Filament;

it('keeps the admin panel and existing resources registered', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getPath())->toBe('admin')
        ->and($panel->getResources())->toContain(
            CategoryResource::class,
            ProductResource::class,
            StockHistoryResource::class,
            UserResource::class,
        );
});

it('keeps the public landing page available before the admin login', function () {
    $this->get('/')->assertOk();
    $this->get('/admin/login')->assertOk();
});
