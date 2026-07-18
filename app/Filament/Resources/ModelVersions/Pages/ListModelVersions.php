<?php

namespace App\Filament\Resources\ModelVersions\Pages;

use App\Filament\Resources\ModelVersions\ModelVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListModelVersions extends ListRecords
{
    protected static string $resource = ModelVersionResource::class;
}
