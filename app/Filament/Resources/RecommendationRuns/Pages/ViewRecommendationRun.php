<?php

namespace App\Filament\Resources\RecommendationRuns\Pages;

use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Filament\Resources\RecommendationRuns\RecommendationRunResource;
use App\Models\RecommendationRun;
use App\Services\Recommendation\RecommendationRunService;
use App\Services\Reporting\RecommendationExportService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewRecommendationRun extends ViewRecord
{
    protected static string $resource = RecommendationRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry')
                ->label('Ulangi Proses')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === RecommendationRunStatus::Failed)
                ->action(fn () => app(RecommendationRunService::class)->start(
                    auth()->user(),
                    queue: true,
                    retryOf: $this->recommendationRun(),
                )),
            Action::make('export')
                ->label('Ekspor CSV')
                ->visible(fn (): bool => $this->getRecord()->status === RecommendationRunStatus::Completed)
                ->action(fn (): StreamedResponse => response()->streamDownload(
                    function (): void {
                        echo app(RecommendationExportService::class)->toCsv($this->recommendationRun());
                    },
                    'rekomendasi-'.$this->recommendationRun()->public_id.'.csv',
                    ['Content-Type' => 'text/csv; charset=UTF-8'],
                )),
        ];
    }

    private function recommendationRun(): RecommendationRun
    {
        /** @var RecommendationRun $run */
        $run = $this->getRecord();

        return $run;
    }
}
