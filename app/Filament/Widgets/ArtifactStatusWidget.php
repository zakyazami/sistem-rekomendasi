<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\RecommendationDashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ArtifactStatusWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Status Artifact dan Metrik Model';

    protected function getStats(): array
    {
        $metrics = app(RecommendationDashboardService::class)->metrics();
        $modelMetrics = $metrics['model_metrics'];
        $training = $metrics['training_metadata'];

        return [
            Stat::make('Artifact', $metrics['artifact_valid'] ? 'Valid' : 'Tidak Valid')
                ->color($metrics['artifact_valid'] ? 'success' : 'danger')
                ->description($metrics['artifact_error']),
            Stat::make('Model', $metrics['model_name'] ? $metrics['model_name'].' v'.$metrics['model_version'] : '-')
                ->description($metrics['model_checksum'] ? substr($metrics['model_checksum'], 0, 12).'…' : null),
            Stat::make('Threshold', $metrics['threshold'] === null ? '-' : number_format($metrics['threshold'], 4))
                ->description($metrics['feature_count'].' fitur'),
            Stat::make('Waktu Training', $training['trained_at'] ?? $training['training_timestamp'] ?? '-')
                ->description($this->periodDescription($training)),
            $this->metricStat('Accuracy', $modelMetrics, 'accuracy'),
            $this->metricStat('Balanced Accuracy', $modelMetrics, 'balanced_accuracy'),
            $this->metricStat('Precision Perlu Pesan', $modelMetrics, 'precision_perlu_pesan'),
            $this->metricStat('Recall Perlu Pesan', $modelMetrics, 'recall_perlu_pesan'),
            $this->metricStat('F1 Perlu Pesan', $modelMetrics, 'f1_perlu_pesan'),
            $this->metricStat('PR-AUC', $modelMetrics, 'pr_auc'),
            $this->metricStat('ROC-AUC', $modelMetrics, 'roc_auc'),
        ];
    }

    /** @param array<string, mixed> $metrics */
    private function metricStat(string $label, array $metrics, string $key): Stat
    {
        $value = isset($metrics[$key]) ? number_format((float) $metrics[$key], 4) : '-';

        return Stat::make($label, $value);
    }

    /** @param array<string, mixed> $training */
    private function periodDescription(array $training): ?string
    {
        $start = $training['train_period_start'] ?? $training['training_period_start'] ?? null;
        $end = $training['test_period_end'] ?? $training['test_period_end_date'] ?? null;

        return $start && $end ? "Periode {$start} – {$end}" : null;
    }
}
