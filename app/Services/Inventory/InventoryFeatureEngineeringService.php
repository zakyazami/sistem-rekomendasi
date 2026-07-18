<?php

namespace App\Services\Inventory;

use App\Domain\Recommendation\Data\FeatureVector;
use App\Models\StockHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class InventoryFeatureEngineeringService
{
    /**
     * @param  iterable<int, StockHistory>  $histories
     */
    public function build(iterable $histories, int $horizonDays): ?FeatureVector
    {
        /** @var Collection<int, StockHistory> $ordered */
        $ordered = collect($histories)
            ->sortBy(static fn (StockHistory $history): string => sprintf(
                '%s-%020d',
                $history->date->format('Y-m-d'),
                $history->getKey(),
            ))
            ->values();

        if ($ordered->count() < 8) {
            return null;
        }

        /** @var StockHistory $latest */
        $latest = $ordered->last();
        $history = $ordered->slice(0, -1)->values();
        $sales7 = $history->pluck('outgoing_stock')->map(static fn (mixed $value): float => (float) $value)->take(-7)->values();
        $sales30 = $history->pluck('outgoing_stock')->map(static fn (mixed $value): float => (float) $value)->take(-30)->values();
        $average7 = $this->mean($sales7);
        $average30 = $this->mean($sales30);
        $currentStock = (float) $latest->final_stock;
        $dataDate = CarbonImmutable::parse($latest->date->format('Y-m-d'));

        return new FeatureVector(
            values: [
                'stok_akhir' => $currentStock,
                'barang_keluar' => (float) $latest->outgoing_stock,
                'rata_penjualan_7' => $average7,
                'std_penjualan_7' => $this->populationStandardDeviation($sales7, $average7),
                'rata_penjualan_30' => $average30,
                'std_penjualan_30' => $this->populationStandardDeviation($sales30, $average30),
                'cakupan_stok_hari' => $average30 > 0.0 ? $currentStock / $average30 : null,
                'hari_dalam_minggu' => (float) ($dataDate->dayOfWeekIso - 1),
                'horizon_hari_target' => (float) $horizonDays,
            ],
            dataDate: $dataDate,
            historyCount: $ordered->count(),
            currentStock: $currentStock,
            currentOutgoingStock: (float) $latest->outgoing_stock,
        );
    }

    /** @param Collection<int, float> $values */
    private function mean(Collection $values): float
    {
        return $values->isEmpty() ? 0.0 : (float) ($values->sum() / $values->count());
    }

    /** @param Collection<int, float> $values */
    private function populationStandardDeviation(Collection $values, float $mean): float
    {
        if ($values->isEmpty()) {
            return 0.0;
        }

        $squaredDifferences = $values->sum(
            static fn (float $value): float => ($value - $mean) ** 2,
        );

        return sqrt($squaredDifferences / $values->count());
    }
}
