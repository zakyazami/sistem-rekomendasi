<?php

namespace App\Filament\Resources\StockRecommendations;

use App\Filament\Resources\StockRecommendations\Pages\ListStockRecommendations;
use App\Filament\Resources\StockRecommendations\Pages\ViewStockRecommendation;
use App\Models\StockRecommendation;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class StockRecommendationResource extends Resource
{
    protected static ?string $model = StockRecommendation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Model & Rekomendasi';

    protected static ?string $navigationLabel = 'Hasil Rekomendasi';

    protected static ?string $pluralModelLabel = 'hasil rekomendasi';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.sku')->label('SKU')->searchable(),
                TextColumn::make('product.name')->label('Produk')->searchable()->sortable(),
                TextColumn::make('product.category.name')->label('Kategori')->sortable(),
                TextColumn::make('data_date')->label('Tanggal Data')->date('d M Y')->sortable(),
                TextColumn::make('model_probability_positive')->label('Probabilitas')->numeric(6),
                IconColumn::make('inventory_trigger')->label('Trigger')->boolean(),
                TextColumn::make('final_recommendation')->label('Rekomendasi')->badge(),
                TextColumn::make('recommended_quantity')->label('Jumlah')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('final_recommendation')
                    ->label('Rekomendasi')
                    ->options(['Perlu Pesan' => 'Perlu Pesan', 'Tidak' => 'Tidak', 'Data Tidak Cukup' => 'Data Tidak Cukup']),
                SelectFilter::make('product.category_id')
                    ->label('Kategori')
                    ->relationship('product.category', 'name'),
                SelectFilter::make('recommendation_run_id')
                    ->label('Proses')
                    ->relationship('run', 'public_id'),
            ])
            ->defaultSort('recommended_quantity', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('product.sku')->label('SKU'),
            TextEntry::make('product.name')->label('Produk'),
            TextEntry::make('data_date')->label('Tanggal Data')->date('d M Y'),
            TextEntry::make('current_stock')->label('Stok Saat Ini'),
            TextEntry::make('on_order_quantity')->label('Dalam Pemesanan'),
            TextEntry::make('projected_inventory')->label('Projected Inventory'),
            TextEntry::make('average_sales_7')->label('Rata-rata Penjualan 7'),
            TextEntry::make('std_sales_7')->label('Std. Penjualan 7'),
            TextEntry::make('average_sales_30')->label('Rata-rata Penjualan 30'),
            TextEntry::make('std_sales_30')->label('Std. Penjualan 30'),
            TextEntry::make('safety_stock')->label('Safety Stock'),
            TextEntry::make('reorder_point')->label('Reorder Point'),
            TextEntry::make('target_stock')->label('Target Stock'),
            TextEntry::make('model_probability_positive')->label('Probabilitas Perlu Pesan'),
            TextEntry::make('model_threshold')->label('Threshold'),
            TextEntry::make('model_classification')->label('Klasifikasi Model'),
            TextEntry::make('inventory_trigger')->label('Trigger Persediaan')->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak'),
            TextEntry::make('final_recommendation')->label('Rekomendasi Final')->badge(),
            TextEntry::make('recommended_quantity')->label('Jumlah Disarankan'),
            TextEntry::make('reason_codes')->label('Alasan')->listWithLineBreaks(),
            TextEntry::make('warnings')->label('Peringatan')->listWithLineBreaks(),
            TextEntry::make('run.modelVersion.model_version')->label('Versi Model'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockRecommendations::route('/'),
            'view' => ViewStockRecommendation::route('/{record}'),
        ];
    }
}
