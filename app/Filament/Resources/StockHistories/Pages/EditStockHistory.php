<?php

namespace App\Filament\Resources\StockHistories\Pages;

use App\Filament\Resources\StockHistories\StockHistoryResource;
use App\Models\StockHistory;
use App\Services\Inventory\StockHistoryService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStockHistory extends EditRecord
{
    protected static string $resource = StockHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var StockHistory $record */
        return app(StockHistoryService::class)->update($record, $data);
    }
}
