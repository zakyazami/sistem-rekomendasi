<?php

namespace App\Filament\Resources\RecommendationRuns\Pages;

use App\Filament\Resources\RecommendationRuns\RecommendationRunResource;
use App\Services\MachineLearning\ModelArtifactLoader;
use App\Services\Recommendation\RecommendationRunService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListRecommendationRuns extends ListRecords
{
    protected static string $resource = RecommendationRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('proses_rekomendasi')
                ->label('Proses Rekomendasi')
                ->icon('heroicon-o-play')
                ->requiresConfirmation()
                ->disabled(fn (): bool => ! $this->artifactIsValid())
                ->action(function (): void {
                    app(RecommendationRunService::class)->start(auth()->user(), queue: true);
                    Notification::make()->success()->title('Proses rekomendasi masuk antrean.')->send();
                }),
        ];
    }

    private function artifactIsValid(): bool
    {
        try {
            app(ModelArtifactLoader::class)->load();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
