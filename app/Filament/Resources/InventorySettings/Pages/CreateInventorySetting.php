<?php

namespace App\Filament\Resources\InventorySettings\Pages;

use App\Filament\Resources\InventorySettings\InventorySettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventorySetting extends CreateRecord
{
    protected static string $resource = InventorySettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
