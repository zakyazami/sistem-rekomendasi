<?php

namespace App\Filament\Resources\RecommendationRuns;

use App\Filament\Resources\RecommendationRuns\Pages\ListRecommendationRuns;
use App\Filament\Resources\RecommendationRuns\Pages\ViewRecommendationRun;
use App\Models\RecommendationRun;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RecommendationRunResource extends Resource
{
    protected static ?string $model = RecommendationRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Model & Rekomendasi';

    protected static ?string $navigationLabel = 'Proses Rekomendasi';

    protected static ?string $pluralModelLabel = 'proses rekomendasi';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')->label('ID Proses')->limit(12)->copyable()->searchable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('data_date')->label('Tanggal Data')->date('d M Y')->sortable(),
                TextColumn::make('total_products')->label('Total'),
                TextColumn::make('succeeded_products')->label('Berhasil'),
                TextColumn::make('insufficient_products')->label('Data Kurang'),
                TextColumn::make('triggeredBy.name')->label('Pemicu')->placeholder('Sistem'),
                TextColumn::make('finished_at')->label('Selesai')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('public_id')->label('ID Proses')->copyable(),
            TextEntry::make('status')->label('Status')->badge(),
            TextEntry::make('modelVersion.model_version')->label('Versi Model'),
            TextEntry::make('data_date')->label('Tanggal Data')->date('d M Y'),
            TextEntry::make('total_products')->label('Total Produk'),
            TextEntry::make('processed_products')->label('Diproses'),
            TextEntry::make('succeeded_products')->label('Berhasil'),
            TextEntry::make('failed_products')->label('Gagal'),
            TextEntry::make('insufficient_products')->label('Data Tidak Cukup'),
            TextEntry::make('error_summary')->label('Ringkasan Error')->placeholder('-')->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecommendationRuns::route('/'),
            'view' => ViewRecommendationRun::route('/{record}'),
        ];
    }
}
