<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class StockHistoryService
{
    /** @param array<string, mixed> $data */
    public function create(Product $product, array $data): StockHistory
    {
        return DB::transaction(function () use ($product, $data): StockHistory {
            Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $latest = StockHistory::query()
                ->where('product_id', $product->id)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            $validated = $this->validate(
                $latest === null ? $data : ['initial_stock' => 0, ...$data],
                requireInitialStock: true,
            );

            if ($latest !== null && $validated['date'] <= $latest->date->toDateString()) {
                throw ValidationException::withMessages([
                    'date' => 'Tanggal stok manual harus setelah histori terbaru.',
                ]);
            }

            $initialStock = $latest?->final_stock ?? $validated['initial_stock'];
            $finalStock = $this->calculateFinal(
                (int) $initialStock,
                $validated['incoming_stock'],
                $validated['outgoing_stock'],
            );

            return StockHistory::query()->create([
                'product_id' => $product->id,
                'date' => $validated['date'],
                'initial_stock' => $initialStock,
                'incoming_stock' => $validated['incoming_stock'],
                'outgoing_stock' => $validated['outgoing_stock'],
                'final_stock' => $finalStock,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(StockHistory $history, array $data): StockHistory
    {
        return DB::transaction(function () use ($history, $data): StockHistory {
            /** @var StockHistory $locked */
            $locked = StockHistory::query()->whereKey($history->id)->lockForUpdate()->firstOrFail();
            $latestId = StockHistory::query()
                ->where('product_id', $locked->product_id)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->value('id');

            if ($latestId !== $locked->id) {
                throw ValidationException::withMessages([
                    'date' => 'Hanya histori stok terbaru yang dapat diubah.',
                ]);
            }

            $validated = $this->validate([
                'date' => $locked->date->toDateString(),
                'initial_stock' => $locked->initial_stock,
                'incoming_stock' => $data['incoming_stock'] ?? $locked->incoming_stock,
                'outgoing_stock' => $data['outgoing_stock'] ?? $locked->outgoing_stock,
            ], requireInitialStock: true);
            $finalStock = $this->calculateFinal(
                (int) $locked->initial_stock,
                $validated['incoming_stock'],
                $validated['outgoing_stock'],
            );

            $locked->forceFill([
                'incoming_stock' => $validated['incoming_stock'],
                'outgoing_stock' => $validated['outgoing_stock'],
                'final_stock' => $finalStock,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{date: string, initial_stock: int, incoming_stock: int, outgoing_stock: int}
     */
    private function validate(array $data, bool $requireInitialStock): array
    {
        /** @var array{date: string, initial_stock: int, incoming_stock: int, outgoing_stock: int} $validated */
        $validated = Validator::make($data, [
            'date' => ['required', 'date'],
            'initial_stock' => [$requireInitialStock ? 'required' : 'nullable', 'integer', 'min:0'],
            'incoming_stock' => ['required', 'integer', 'min:0'],
            'outgoing_stock' => ['required', 'integer', 'min:0'],
        ])->validate();

        return $validated;
    }

    private function calculateFinal(int $initial, int $incoming, int $outgoing): int
    {
        $final = $initial + $incoming - $outgoing;

        if ($final < 0) {
            throw ValidationException::withMessages([
                'outgoing_stock' => 'Barang keluar tidak boleh menghasilkan stok negatif.',
            ]);
        }

        return $final;
    }
}
