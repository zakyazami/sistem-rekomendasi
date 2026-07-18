<?php

namespace App\Filament\Resources\InventorySettings\Pages;

use App\Filament\Resources\InventorySettings\InventorySettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventorySettings extends ListRecords
{
    protected static string $resource = InventorySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Parameter')];
    }
}
