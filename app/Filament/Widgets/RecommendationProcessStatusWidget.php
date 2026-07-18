<?php

namespace App\Filament\Widgets;

use App\Domain\Dashboard\Data\DashboardOverview;
use App\Filament\Resources\RecommendationRuns\RecommendationRunResource;
use App\Filament\Resources\StockImports\StockImportResource;
use App\Filament\Resources\StockRecommendations\StockRecommendationResource;
use App\Models\RecommendationRun;
use App\Models\StockImport;
use App\Models\StockRecommendation;
use App\Services\Dashboard\DashboardOverviewService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

class RecommendationProcessStatusWidget extends Widget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.recommendation-process-status-widget';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $overview = app(DashboardOverviewService::class)->get();

        return [
            'overview' => $overview,
            'actionLabel' => $this->actionLabel($overview),
            'actionUrl' => $this->actionUrl($overview),
            'showAction' => $this->showAction($overview),
        ];
    }

    private function actionLabel(DashboardOverview $overview): string
    {
        return match ($overview->nextAction) {
            'import' => 'Import Data',
            'run' => 'Jalankan Sekarang',
            'retry' => 'Coba Lagi',
            default => 'Lihat Detail',
        };
    }

    private function actionUrl(DashboardOverview $overview): string
    {
        return match ($overview->nextAction) {
            'import' => StockImportResource::getUrl('index'),
            'retry' => $overview->latestRun
                ? RecommendationRunResource::getUrl('view', ['record' => $overview->latestRun])
                : RecommendationRunResource::getUrl('index'),
            'results' => StockRecommendationResource::getUrl('index'),
            default => RecommendationRunResource::getUrl('index'),
        };
    }

    private function showAction(DashboardOverview $overview): bool
    {
        return match ($overview->nextAction) {
            'import' => Gate::allows('create', StockImport::class),
            'retry' => $overview->latestRun !== null
                && Gate::allows('retry', $overview->latestRun),
            'results' => Gate::allows('viewAny', StockRecommendation::class),
            default => Gate::allows('create', RecommendationRun::class),
        };
    }
}
