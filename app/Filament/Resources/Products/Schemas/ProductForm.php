<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('moving_type')
                    ->options([
                        'FAST' => 'Fast',
                        'MEDIUM' => 'Medium',
                        'SLOW' => 'Slow',
                        'VERY_SLOW' => 'Very Slow',
                    ])
                    ->required(),
                TextInput::make('minimum_stock')
                    ->required()
                    ->numeric()
                    ->default(10),
                Toggle::make('is_active')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
