<?php

namespace App\Filament\Resources\InventorySettings\Pages;

use App\Filament\Resources\InventorySettings\InventorySettingResource;
use Filament\Resources\Pages\EditRecord;

class EditInventorySetting extends EditRecord
{
    protected static string $resource = InventorySettingResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
