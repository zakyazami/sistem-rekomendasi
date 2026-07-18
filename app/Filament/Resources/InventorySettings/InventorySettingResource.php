<?php

namespace App\Filament\Resources\InventorySettings;

use App\Filament\Resources\InventorySettings\Pages\CreateInventorySetting;
use App\Filament\Resources\InventorySettings\Pages\EditInventorySetting;
use App\Filament\Resources\InventorySettings\Pages\ListInventorySettings;
use App\Models\InventorySetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class InventorySettingResource extends Resource
{
    protected static ?string $model = InventorySetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Parameter Persediaan';

    protected static ?string $modelLabel = 'parameter persediaan';

    protected static ?string $pluralModelLabel = 'parameter persediaan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('scope_key')
                ->label('Kunci cakupan')
                ->helperText('Gunakan global untuk default seluruh produk.')
                ->required()
                ->maxLength(64),
            Select::make('product_id')
                ->label('Produk khusus')
                ->relationship('product', 'name')
                ->searchable()
                ->preload(),
            TextInput::make('lead_time_days')
                ->label('Lead time (hari)')
                ->integer()
                ->minValue(0)
                ->required(),
            TextInput::make('review_period_days')
                ->label('Periode tinjau (hari)')
                ->integer()
                ->minValue(1)
                ->required(),
            TextInput::make('service_level')
                ->label('Service level')
                ->numeric()
                ->minValue(0.5)
                ->maxValue(0.9999)
                ->required(),
            TextInput::make('prediction_horizon_days')
                ->label('Horizon prediksi (hari)')
                ->integer()
                ->minValue(1)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scope_key')->label('Cakupan')->searchable(),
                TextColumn::make('product.name')->label('Produk')->placeholder('Semua produk'),
                TextColumn::make('lead_time_days')->label('Lead time'),
                TextColumn::make('review_period_days')->label('Periode tinjau'),
                TextColumn::make('service_level')->label('Service level'),
                TextColumn::make('prediction_horizon_days')->label('Horizon'),
                TextColumn::make('updated_at')->label('Diperbarui')->dateTime('d M Y H:i')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventorySettings::route('/'),
            'create' => CreateInventorySetting::route('/create'),
            'edit' => EditInventorySetting::route('/{record}/edit'),
        ];
    }
}
