<?php

namespace App\Services\Dashboard;

use App\Domain\Dashboard\Data\DashboardOverview;
use App\Domain\Recommendation\Enums\RecommendationLabel;
use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Models\RecommendationRun;
use Carbon\CarbonImmutable;

final readonly class DashboardOverviewService
{
    public function __construct(
        private RecommendationDashboardService $dashboard,
        private IndonesianDashboardFormatter $formatter,
    ) {}

    public function get(): DashboardOverview
    {
        $metrics = $this->dashboard->metrics();
        $latestRun = $metrics['latest_run'];
        $latestCompletedRun = $metrics['latest_completed_run'];
        $hasData = $metrics['latest_data_date'] !== null;
        $hasCompletedRun = $latestCompletedRun instanceof RecommendationRun;
        [$freshnessLabel, $freshnessColor] = $this->freshness($metrics['latest_data_date']);
        [$runStatusLabel, $runStatusColor] = $this->runStatus($latestRun);
        [$nextAction, $emptyHeading, $emptyDescription] = $this->emptyState(
            $hasData,
            $latestRun,
            $hasCompletedRun,
        );
        $modelMetrics = $metrics['model_metrics'];
        $training = $metrics['training_metadata'];

        return new DashboardOverview(
            activeProducts: $metrics['active_products'],
            activeProductsLabel: $this->formatter->integer($metrics['active_products']),
            latestDataDate: $metrics['latest_data_date'],
            latestDataDateLabel: $this->formatter->date($metrics['latest_data_date']),
            freshnessLabel: $freshnessLabel,
            freshnessColor: $freshnessColor,
            needsOrder: $hasCompletedRun ? $metrics['needs_order'] : null,
            needsOrderLabel: $hasCompletedRun ? $this->formatter->integer($metrics['needs_order']) : 'Belum dihitung',
            totalRecommendedQuantity: $hasCompletedRun ? $metrics['total_recommended_quantity'] : null,
            totalRecommendedQuantityLabel: $hasCompletedRun
                ? $this->formatter->integer($metrics['total_recommended_quantity'])
                : 'Belum dihitung',
            artifactValid: $metrics['artifact_valid'],
            artifactStatusLabel: $metrics['artifact_valid'] ? 'Artifact Valid' : 'Artifact Tidak Valid',
            artifactColor: $metrics['artifact_valid'] ? 'success' : 'danger',
            modelName: $metrics['artifact_valid'] ? 'Naive Bayes Rekomendasi Stok' : 'Model tidak tersedia',
            modelVersionLabel: $metrics['model_version'] ? 'v'.$metrics['model_version'] : '-',
            thresholdLabel: $this->formatter->percentage($metrics['threshold']),
            trainedAtLabel: $this->formatter->dateTime($training['trained_at'] ?? null),
            checksumShort: $metrics['model_checksum'] ? substr($metrics['model_checksum'], 0, 12) : '-',
            checksumFull: $metrics['model_checksum'],
            mainMetrics: $this->mainMetrics($modelMetrics),
            advancedMetrics: $this->advancedMetrics($modelMetrics),
            latestRun: $latestRun,
            runStatusLabel: $runStatusLabel,
            runStatusColor: $runStatusColor,
            runTimeLabel: $this->formatter->dateTime(
                $latestRun?->finished_at?->toIso8601String()
                    ?? $latestRun?->started_at?->toIso8601String(),
            ),
            runDurationLabel: $this->formatter->duration(
                $latestRun?->started_at?->toIso8601String(),
                $latestRun?->finished_at?->toIso8601String(),
            ),
            runProcessedProducts: (int) ($latestRun?->processed_products ?? 0),
            runNeedsOrder: $latestRun?->status === RecommendationRunStatus::Completed
                ? $latestRun->recommendations()->where('final_recommendation', RecommendationLabel::NeedsOrder->value)->count()
                : 0,
            runError: $latestRun?->error_summary,
            nextAction: $nextAction,
            emptyStateHeading: $emptyHeading,
            emptyStateDescription: $emptyDescription,
        );
    }

    /** @return array{string, string} */
    private function freshness(?string $latestDate): array
    {
        if ($latestDate === null) {
            return ['Belum ada data', 'danger'];
        }

        $days = CarbonImmutable::parse($latestDate)->startOfDay()->diffInDays(CarbonImmutable::now()->startOfDay());

        return match (true) {
            $days <= 7 => ['Data terbaru', 'success'],
            $days <= 30 => ['Data mulai lama', 'warning'],
            default => ['Data kedaluwarsa', 'danger'],
        };
    }

    /** @return array{string, string} */
    private function runStatus(?RecommendationRun $run): array
    {
        if ($run === null) {
            return ['Belum pernah', 'gray'];
        }

        return match ($run->status) {
            RecommendationRunStatus::Pending => ['Antre', 'warning'],
            RecommendationRunStatus::Running => ['Berjalan', 'info'],
            RecommendationRunStatus::Completed => ['Berhasil', 'success'],
            RecommendationRunStatus::Failed => ['Gagal', 'danger'],
        };
    }

    /** @return array{string, string, string} */
    private function emptyState(bool $hasData, ?RecommendationRun $latestRun, bool $hasCompletedRun): array
    {
        if (! $hasData) {
            return [
                'import',
                'Belum ada data persediaan.',
                'Impor dataset transaksi terlebih dahulu agar sistem dapat menghitung rekomendasi.',
            ];
        }

        if ($latestRun?->status === RecommendationRunStatus::Failed) {
            return [
                'retry',
                'Proses rekomendasi terakhir gagal.',
                'Periksa ringkasan kegagalan lalu coba jalankan kembali proses rekomendasi.',
            ];
        }

        if (! $hasCompletedRun) {
            return [
                'run',
                'Rekomendasi belum pernah dijalankan.',
                'Jalankan proses rekomendasi untuk melihat prioritas pemesanan.',
            ];
        }

        return ['results', 'Tidak ada prioritas pemesanan.', 'Stok pada proses terbaru belum memerlukan pemesanan.'];
    }

    /** @param array<string, mixed> $metrics @return array<string, array{label: string, value: string, tooltip: string}> */
    private function mainMetrics(array $metrics): array
    {
        return [
            'accuracy' => $this->metric('Accuracy', $metrics['accuracy'] ?? null, 'Proporsi seluruh prediksi test-set yang benar.'),
            'precision' => $this->metric('Precision Perlu Pesan', $metrics['precision_perlu_pesan'] ?? null, 'Dari prediksi perlu pesan, persentase yang benar-benar positif.'),
            'recall' => $this->metric('Recall Perlu Pesan', $metrics['recall_perlu_pesan'] ?? null, 'Dari seluruh kasus positif, persentase yang berhasil ditemukan model.'),
            'f1' => $this->metric('F1-Score Perlu Pesan', $metrics['f1_perlu_pesan'] ?? null, 'Keseimbangan harmonis antara precision dan recall.'),
        ];
    }

    /** @param array<string, mixed> $metrics @return array<string, array{label: string, value: string, tooltip: string}> */
    private function advancedMetrics(array $metrics): array
    {
        return [
            'balanced_accuracy' => $this->metric('Balanced Accuracy', $metrics['balanced_accuracy'] ?? null, 'Rata-rata recall tiap kelas agar kelas minoritas tetap diperhitungkan.'),
            'pr_auc' => $this->metric('PR-AUC', $metrics['pr_auc'] ?? null, 'Luas kurva precision-recall, relevan untuk data tidak seimbang.'),
            'roc_auc' => $this->metric('ROC-AUC', $metrics['roc_auc'] ?? null, 'Kemampuan model membedakan dua kelas pada berbagai threshold.'),
        ];
    }

    /** @return array{label: string, value: string, tooltip: string} */
    private function metric(string $label, mixed $value, string $tooltip): array
    {
        return [
            'label' => $label,
            'value' => $this->formatter->percentage(is_numeric($value) ? (float) $value : null),
            'tooltip' => $tooltip,
        ];
    }
}
