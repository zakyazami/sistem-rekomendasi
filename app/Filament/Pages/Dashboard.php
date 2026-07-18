<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StockImports\StockImportResource;
use App\Filament\Resources\StockRecommendations\StockRecommendationResource;
use App\Filament\Widgets\InventoryStatsWidget;
use App\Filament\Widgets\ModelHealthOverviewWidget;
use App\Filament\Widgets\RecommendationProcessStatusWidget;
use App\Filament\Widgets\TopPriorityRecommendations;
use App\Models\RecommendationRun;
use App\Models\StockImport;
use App\Models\StockRecommendation;
use App\Services\Dashboard\DashboardOverviewService;
use App\Services\Recommendation\RecommendationRunService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard Persediaan';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pantau kondisi stok dan rekomendasi pemesanan terbaru.';
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function getWidgets(): array
    {
        return [
            InventoryStatsWidget::class,
            RecommendationProcessStatusWidget::class,
            ModelHealthOverviewWidget::class,
            TopPriorityRecommendations::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_data')
                ->label('Import Data')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(fn (): string => StockImportResource::getUrl('index'))
                ->visible(fn (): bool => Gate::allows('create', StockImport::class)),
            Action::make('run_recommendations')
                ->label('Jalankan Rekomendasi')
                ->icon('heroicon-o-play')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('create', RecommendationRun::class))
                ->disabled(fn (): bool => ! $this->recommendationCanRun())
                ->tooltip(fn (): ?string => $this->recommendationDisabledReason())
                ->action(function (): void {
                    app(RecommendationRunService::class)->start(auth()->user(), queue: true);
                    Notification::make()->success()->title('Proses rekomendasi masuk antrean.')->send();
                }),
            Action::make('view_results')
                ->label('Lihat Semua Hasil')
                ->icon('heroicon-o-list-bullet')
                ->url(fn (): string => StockRecommendationResource::getUrl('index'))
                ->visible(fn (): bool => Gate::allows('viewAny', StockRecommendation::class)),
        ];
    }

    private function recommendationCanRun(): bool
    {
        $overview = app(DashboardOverviewService::class)->get();

        return $overview->artifactValid && $overview->latestDataDate !== null;
    }

    private function recommendationDisabledReason(): ?string
    {
        $overview = app(DashboardOverviewService::class)->get();

        return match (true) {
            ! $overview->artifactValid => 'Artifact model tidak valid. Periksa deployment model.',
            $overview->latestDataDate === null => 'Import data persediaan sebelum menjalankan rekomendasi.',
            default => null,
        };
    }
}
