<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RecommendationRuns\RecommendationRunResource;
use App\Filament\Resources\StockImports\StockImportResource;
use App\Models\RecommendationRun;
use App\Models\StockImport;
use App\Models\StockRecommendation;
use App\Services\Dashboard\DashboardOverviewService;
use App\Services\Dashboard\IndonesianDashboardFormatter;
use App\Services\Dashboard\LatestRecommendationQuery;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;

class TopPriorityRecommendations extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $overview = app(DashboardOverviewService::class)->get();
        $query = app(LatestRecommendationQuery::class);
        $formatter = app(IndonesianDashboardFormatter::class);

        return $table
            ->heading('Prioritas Pemesanan Teratas')
            ->description('Hasil perlu pesan dari proses rekomendasi berhasil terbaru.')
            ->query($query->queryForLatestCompleted()->limit(10))
            ->columns([
                TextColumn::make('product.sku')->label('SKU')->visibleFrom('sm'),
                TextColumn::make('product.name')->label('Produk')->wrap(),
                TextColumn::make('product.category.name')->label('Kategori')->visibleFrom('md'),
                TextColumn::make('projected_inventory')
                    ->label('Stok Proyeksi')
                    ->numeric(2)
                    ->tooltip('Perkiraan stok setelah kebutuhan selama horizon prediksi.')
                    ->visibleFrom('lg'),
                TextColumn::make('reorder_point')
                    ->label('Titik Pesan Ulang')
                    ->tooltip('Batas stok operasional yang memicu evaluasi pemesanan.')
                    ->visibleFrom('lg'),
                TextColumn::make('model_probability_positive')
                    ->label('Probabilitas')
                    ->formatStateUsing(fn (?float $state): string => $formatter->percentage($state))
                    ->tooltip('Probabilitas kelas Perlu Pesan dari model Naive Bayes.')
                    ->visibleFrom('md'),
                TextColumn::make('inventory_trigger')
                    ->label('Alasan/Trigger')
                    ->formatStateUsing(fn (StockRecommendation $record): string => $query->triggerLabel($record))
                    ->badge()
                    ->color(fn (StockRecommendation $record): string => $record->inventory_trigger ? 'warning' : 'info'),
                TextColumn::make('recommended_quantity')
                    ->label('Jumlah Pesan')
                    ->formatStateUsing(fn (int $state): string => $formatter->integer($state))
                    ->weight(FontWeight::Bold)
                    ->color('danger'),
            ])
            ->paginated(false)
            ->emptyStateHeading($overview->emptyStateHeading)
            ->emptyStateDescription($overview->emptyStateDescription)
            ->emptyStateActions([
                Action::make('import_data')
                    ->label('Import Data')
                    ->url(StockImportResource::getUrl('index'))
                    ->visible($overview->nextAction === 'import' && Gate::allows('create', StockImport::class)),
                Action::make('run_recommendations')
                    ->label($overview->nextAction === 'retry' ? 'Coba Lagi' : 'Jalankan Rekomendasi')
                    ->url($overview->latestRun && $overview->nextAction === 'retry'
                        ? RecommendationRunResource::getUrl('view', ['record' => $overview->latestRun])
                        : RecommendationRunResource::getUrl('index'))
                    ->visible(in_array($overview->nextAction, ['run', 'retry'], true)
                        && Gate::allows('create', RecommendationRun::class)),
            ]);
    }
}
