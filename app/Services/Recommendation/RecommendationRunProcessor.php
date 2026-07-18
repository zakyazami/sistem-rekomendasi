<?php

namespace App\Services\Recommendation;

use App\Contracts\RecommendationEngine;
use App\Domain\Recommendation\Data\RecommendationResult;
use App\Domain\Recommendation\Enums\RecommendationItemStatus;
use App\Domain\Recommendation\Enums\RecommendationLabel;
use App\Domain\Recommendation\Enums\RecommendationRunStatus;
use App\Models\Product;
use App\Models\RecommendationRun;
use App\Models\StockHistory;
use App\Models\StockRecommendation;
use App\Services\MachineLearning\ModelArtifactLoader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class RecommendationRunProcessor
{
    public function __construct(
        private RecommendationEngine $engine,
        private ModelArtifactLoader $artifactLoader,
        private InventorySettingResolver $settingResolver,
        private StockHistoryWindowRepository $historyRepository,
    ) {}

    public function process(RecommendationRun $run): RecommendationRun
    {
        if ($run->status === RecommendationRunStatus::Completed) {
            return $run;
        }

        $run->forceFill([
            'status' => RecommendationRunStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'error_summary' => null,
        ])->save();

        try {
            $artifact = $this->artifactLoader->load();
            $products = Product::query()->where('is_active', true)->orderBy('id')->get();
            $historiesByProduct = $this->historyRepository->latestForProducts(
                $products->modelKeys(),
            );
            $rows = [];
            $succeeded = 0;
            $insufficient = 0;
            $dataDates = [];

            foreach ($products as $product) {
                /** @var Collection<int, StockHistory> $histories */
                $histories = $historiesByProduct->get($product->id, collect());
                $parameters = $this->settingResolver->forProduct($product);
                $result = $this->engine->recommendHistories($histories, $parameters);

                if ($result === null) {
                    $rows[] = $this->insufficientRow($run, $product, $histories, $parameters->onOrderQuantity);
                    $insufficient++;

                    continue;
                }

                $rows[] = $this->successRow($run, $product, $result, $artifact->threshold, $parameters->onOrderQuantity);
                $dataDates[] = $result->featureVector->dataDate->toDateString();
                $succeeded++;
            }

            DB::transaction(function () use ($run, $rows, $products, $succeeded, $insufficient, $dataDates): void {
                StockRecommendation::query()->where('recommendation_run_id', $run->id)->delete();

                foreach ($rows as $row) {
                    StockRecommendation::query()->create($row);
                }

                $run->forceFill([
                    'status' => RecommendationRunStatus::Completed,
                    'finished_at' => now(),
                    'data_date' => $dataDates === [] ? null : max($dataDates),
                    'total_products' => $products->count(),
                    'processed_products' => count($rows),
                    'succeeded_products' => $succeeded,
                    'failed_products' => 0,
                    'insufficient_products' => $insufficient,
                    'error_summary' => null,
                ])->save();
            });

            return $run->refresh();
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => RecommendationRunStatus::Failed,
                'finished_at' => now(),
                'error_summary' => 'Proses rekomendasi gagal. Periksa log aplikasi.',
            ])->save();

            Log::error('Recommendation run failed.', [
                'run_id' => $run->public_id,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  Collection<int, StockHistory>  $histories
     * @return array<string, mixed>
     */
    private function insufficientRow(
        RecommendationRun $run,
        Product $product,
        Collection $histories,
        int $onOrderQuantity,
    ): array {
        /** @var StockHistory|null $latest */
        $latest = $histories->last();

        return [
            'recommendation_run_id' => $run->id,
            'product_id' => $product->id,
            'item_status' => RecommendationItemStatus::InsufficientData,
            'data_date' => $latest?->date?->toDateString(),
            'history_count' => $histories->count(),
            'current_stock' => $latest?->final_stock,
            'current_outgoing_stock' => $latest?->outgoing_stock,
            'on_order_quantity' => $onOrderQuantity,
            'final_recommendation' => RecommendationLabel::InsufficientData->value,
            'recommended_quantity' => 0,
            'reason_codes' => ['minimum_history_not_met'],
            'warnings' => ['Minimal delapan record histori diperlukan.'],
            'feature_payload' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function successRow(
        RecommendationRun $run,
        Product $product,
        RecommendationResult $result,
        float $threshold,
        int $onOrderQuantity,
    ): array {
        $features = $result->featureVector->values;

        return [
            'recommendation_run_id' => $run->id,
            'product_id' => $product->id,
            'item_status' => RecommendationItemStatus::Success,
            'data_date' => $result->featureVector->dataDate->toDateString(),
            'history_count' => $result->featureVector->historyCount,
            'current_stock' => $result->featureVector->currentStock,
            'current_outgoing_stock' => $result->featureVector->currentOutgoingStock,
            'on_order_quantity' => $onOrderQuantity,
            'average_sales_7' => $features['rata_penjualan_7'],
            'std_sales_7' => $features['std_penjualan_7'],
            'average_sales_30' => $features['rata_penjualan_30'],
            'std_sales_30' => $features['std_penjualan_30'],
            'stock_coverage_days' => $features['cakupan_stok_hari'],
            'weekday' => (int) $features['hari_dalam_minggu'],
            'horizon_days' => (int) $features['horizon_hari_target'],
            'inventory_position' => $result->inventoryPosition,
            'projected_inventory' => $result->projectedInventory,
            'safety_stock' => $result->safetyStock,
            'reorder_point' => $result->reorderPoint,
            'target_stock' => $result->targetStock,
            'joint_log_likelihood_0' => $result->prediction->jointLogLikelihoods[0],
            'joint_log_likelihood_1' => $result->prediction->jointLogLikelihoods[1],
            'model_probability_0' => $result->prediction->probabilities[0],
            'model_probability_positive' => $result->prediction->positiveProbability,
            'model_threshold' => $threshold,
            'model_classification' => $result->prediction->predictedLabel,
            'inventory_trigger' => $result->inventoryTrigger,
            'final_recommendation' => $result->finalLabel->value,
            'recommended_quantity' => $result->recommendedQuantity,
            'reason_codes' => $result->reasonCodes,
            'warnings' => $result->warnings,
            'feature_payload' => [
                'raw' => $features,
                'transformed' => $result->prediction->transformedFeatures,
            ],
        ];
    }
}
