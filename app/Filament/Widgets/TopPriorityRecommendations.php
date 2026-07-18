<?php

namespace App\Filament\Widgets;

use App\Domain\Recommendation\Enums\RecommendationLabel;
use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Models\RecommendationRun;
use App\Models\StockRecommendation;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopPriorityRecommendations extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Prioritas Pemesanan Teratas')
            ->query($this->query())
            ->columns([
                TextColumn::make('product.sku')->label('SKU'),
                TextColumn::make('product.name')->label('Produk')->searchable(),
                TextColumn::make('product.category.name')->label('Kategori'),
                TextColumn::make('projected_inventory')->label('Projected Inventory')->numeric(2),
                TextColumn::make('reorder_point')->label('Reorder Point'),
                TextColumn::make('model_probability_positive')->label('Probabilitas')->numeric(6),
                IconColumn::make('inventory_trigger')->label('Trigger')->boolean(),
                TextColumn::make('recommended_quantity')->label('Jumlah')->numeric(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada rekomendasi perlu pesan');
    }

    private function query(): Builder
    {
        $latestRunId = RecommendationRun::query()
            ->where('status', RecommendationRunStatus::Completed->value)
            ->latest('finished_at')
            ->value('id');

        return StockRecommendation::query()
            ->with(['product.category'])
            ->when($latestRunId === null, fn (Builder $query): Builder => $query->whereRaw('1 = 0'))
            ->when($latestRunId !== null, fn (Builder $query): Builder => $query->where('recommendation_run_id', $latestRunId))
            ->where('final_recommendation', RecommendationLabel::NeedsOrder->value)
            ->orderByDesc('inventory_trigger')
            ->orderByDesc('recommended_quantity')
            ->orderByDesc('model_probability_positive')
            ->limit(10);
    }
}
