<?php

namespace App\Services\Recommendation;

use App\Models\StockHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StockHistoryWindowRepository
{
    /**
     * @param  list<int>  $productIds
     * @return Collection<int, Collection<int, StockHistory>>
     */
    public function latestForProducts(array $productIds, int $limit = 31): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $ranked = DB::table('stock_histories')
            ->select('stock_histories.*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY date DESC, id DESC) AS history_rank')
            ->whereIn('product_id', $productIds);

        $rows = DB::query()
            ->fromSub($ranked, 'ranked_histories')
            ->where('history_rank', '<=', $limit)
            ->orderBy('product_id')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return $rows
            ->map(function (object $row): StockHistory {
                $attributes = (array) $row;
                unset($attributes['history_rank']);

                $model = new StockHistory;
                $model->setRawAttributes($attributes, true);
                $model->exists = true;

                return $model;
            })
            ->groupBy(static fn (StockHistory $history): int => (int) $history->product_id);
    }
}
