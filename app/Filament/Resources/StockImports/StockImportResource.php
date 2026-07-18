<?php

namespace App\Filament\Resources\StockImports;

use App\Filament\Resources\StockImports\Pages\ListStockImports;
use App\Models\StockImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class StockImportResource extends Resource
{
    protected static ?string $model = StockImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Import Data Stok';

    protected static ?string $pluralModelLabel = 'riwayat import';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_name')->label('Nama File')->searchable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('mode')->label('Mode'),
                TextColumn::make('total_rows')->label('Total'),
                TextColumn::make('inserted_rows')->label('Ditambahkan'),
                TextColumn::make('updated_rows')->label('Diperbarui'),
                TextColumn::make('skipped_rows')->label('Dilewati'),
                TextColumn::make('failed_rows')->label('Gagal'),
                TextColumn::make('user.name')->label('Pengguna')->placeholder('Sistem'),
                TextColumn::make('finished_at')->label('Selesai')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListStockImports::route('/')];
    }
}
