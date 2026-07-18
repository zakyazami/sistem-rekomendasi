<?php

namespace App\Filament\Resources\StockHistories\Pages;

use App\Filament\Resources\StockHistories\StockHistoryResource;
use App\Models\Product;
use App\Services\Inventory\StockHistoryService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStockHistory extends CreateRecord
{
    protected static string $resource = StockHistoryResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $product = Product::query()->findOrFail($data['product_id']);

        return app(StockHistoryService::class)->create($product, $data);
    }
}
