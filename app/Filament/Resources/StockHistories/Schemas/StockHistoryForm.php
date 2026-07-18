<?php

namespace App\Filament\Resources\StockHistories\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StockHistoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required(),
                Select::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('initial_stock')
                    ->label('Stok Awal')
                    ->required()
                    ->numeric()
                    ->live()
                    ->afterStateUpdated(function ($get, $set) {
                        $set(
                            'final_stock',
                            ($get('initial_stock') ?? 0)
                            +
                            ($get('incoming_stock') ?? 0)
                            -
                            ($get('outgoing_stock') ?? 0)
                        );
                    }),
                TextInput::make('incoming_stock')
                    ->label('Barang Masuk')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->live(),
                TextInput::make('outgoing_stock')
                    ->label('Barang Keluar')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($get, $set) {
                        $set(
                            'final_stock',
                            ($get('initial_stock') ?? 0)
                            +
                            ($get('incoming_stock') ?? 0)
                            -
                            ($get('outgoing_stock') ?? 0)
                        );
                    }),
                TextInput::make('final_stock')
                    ->label('Stok Akhir')
                    ->required()
                    ->dehydrated()
                    ->disabled()
                    ->numeric()
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record) {
                            $component->state($record->final_stock);
                        }
                    }),
            ]);
    }
}
