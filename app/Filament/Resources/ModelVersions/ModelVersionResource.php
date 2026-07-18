<?php

namespace App\Filament\Resources\ModelVersions;

use App\Filament\Resources\ModelVersions\Pages\ListModelVersions;
use App\Models\ModelVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ModelVersionResource extends Resource
{
    protected static ?string $model = ModelVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'Model & Rekomendasi';

    protected static ?string $navigationLabel = 'Versi Model';

    protected static ?string $pluralModelLabel = 'versi model';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('model_name')->label('Model')->searchable(),
            TextColumn::make('model_version')->label('Versi'),
            TextColumn::make('schema_version')->label('Skema'),
            TextColumn::make('threshold')->label('Threshold')->numeric(4),
            TextColumn::make('artifact_file_checksum')
                ->label('Checksum')
                ->limit(12)
                ->copyable(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
            TextColumn::make('created_at')->label('Terdaftar')->dateTime('d M Y H:i')->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListModelVersions::route('/')];
    }
}
