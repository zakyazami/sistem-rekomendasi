<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama Produk')
                    ->required(),
                Select::make('moving_type')
                    ->label('Kecepatan Pergerakan')
                    ->options([
                        'FAST' => 'Fast',
                        'MEDIUM' => 'Medium',
                        'SLOW' => 'Slow',
                        'VERY_SLOW' => 'Very Slow',
                    ])
                    ->required(),
                TextInput::make('minimum_stock')
                    ->label('Stok Minimum')
                    ->required()
                    ->numeric()
                    ->default(10),
                TextInput::make('on_order_quantity')
                    ->label('Barang Dalam Pemesanan')
                    ->helperText('Jumlah barang yang sudah dipesan tetapi belum diterima.')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Produk Aktif')
                    ->required(),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull(),
            ]);
    }
}
